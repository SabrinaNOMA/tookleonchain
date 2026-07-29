<?php
declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\MessageFormatter;
use Psr\Http\Message\RequestInterface;

final class SumsubSignatureMiddleware
{
    public function __construct(
        private readonly string $appToken,
        private readonly string $appSecret
    ) {}

    public function __invoke(callable $handler)
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            $ts = (string) time();
            $bodyStream = $request->getBody();
            $body = (string) $bodyStream;
            if ($bodyStream->isSeekable()) $bodyStream->rewind();

            $method = strtoupper($request->getMethod());
            $uri    = $request->getUri();

            $path = $uri->getPath();
            if ($path === '' || $path[0] !== '/') $path = '/' . $path;
            $query = $uri->getQuery();
            $pathWithQuery = $path . ($query !== '' ? '?' . $query : '');

            //$toSign = $ts . $method . $pathWithQuery + $body;
            //$sig = strtolower(hash_hmac('sha256', $toSign, $this->appSecret));

           // Signature HMAC (hex minuscule)
		   // IMPORTANT: concaténation en PHP = ".", pas "+"
            $toSign = $ts . $method . $pathWithQuery . $body;
            $sig = strtolower(hash_hmac('sha256', $toSign, $this->appSecret));


            $request = $request
                ->withHeader('X-App-Token', $this->appToken)
                ->withHeader('X-App-Access-Ts', $ts)
                ->withHeader('X-App-Access-Sig', $sig);

            if (in_array($method, ['POST','PUT','PATCH'], true) && !$request->hasHeader('Content-Type')) {
                $request = $request->withHeader('Content-Type', 'application/json');
            }
            return $handler($request, $options);
        };
    }
}

final class SumsubApiException extends \RuntimeException
{
    public function __construct(
        public readonly int $statusCode,
        public readonly ?array $responseJson,
        string $message = 'Sumsub API error'
    ) { parent::__construct($message . ' (HTTP ' . $statusCode . ')'); }
}

final class SumsubClient
{
    private Client $http;
    public function __construct(
        string $appToken,
        string $appSecret,
        string $baseUri = 'https://api.sumsub.com',
        ?\Psr\Log\LoggerInterface $logger = null,
        int $maxRetries = 3
    ) {
        $stack = HandlerStack::create();
        $stack->push(Middleware::retry(
            function ($retries,$request,$response=null,$exception=null) use ($maxRetries){
                if ($retries >= $maxRetries) return false;
                if ($exception instanceof \Throwable) return true;
                if ($response) { $c=$response->getStatusCode(); return $c==429 || ($c>=500 && $c<=599); }
                return false;
            },
            function ($retries,$response){ return (int)(pow(2,$retries)*250 + random_int(0,200)); }
        ));
        $stack->push(new SumsubSignatureMiddleware($appToken,$appSecret),'sumsub_sign');
        if ($logger) $stack->push(Middleware::log($logger,new MessageFormatter('{method} {uri} - {code} {res_body}')));

        $this->http = new Client([
            'base_uri'=>rtrim($baseUri,'/').'/',
            'handler'=>$stack,
            'timeout'=>30,
            'http_errors'=>false,
            'verify'=> getenv('SUMSUB_CAINFO') ?: true,
        ]);
    }

    public function createApplicant(array $payload,string $levelName): array {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $uri  = 'resources/applicants?levelName=' . rawurlencode($levelName);
        return $this->requestJson('POST',$uri,['body'=>$body,'headers'=>['Content-Type'=>'application/json']]);
    }
    public function generateSdkAccessToken(string $externalUserId,string $levelName,int $ttlInSecs=900,array $ids=[]): array{
        $payload = ['userId'=>$externalUserId,'levelName'=>$levelName,'ttlInSecs'=>$ttlInSecs];
        if ($ids) $payload['applicantIdentifiers']=$ids;
        $body=json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        return $this->requestJson('POST','resources/accessTokens/sdk',['body'=>$body,'headers'=>['Content-Type'=>'application/json']]);
    }
    public function generateWebsdkLink(string $externalUserId,string $levelName,int $ttlInSecs=1800,array $ids=[]): array{
        $payload = ['levelName'=>$levelName,'userId'=>$externalUserId,'ttlInSecs'=>$ttlInSecs];
        if ($ids) $payload['applicantIdentifiers']=$ids;
        $body=json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        return $this->requestJson('POST','resources/sdkIntegrations/levels/-/websdkLink',['body'=>$body,'headers'=>['Content-Type'=>'application/json']]);
    }
    public function getApplicant(string $applicantId): array {
        return $this->requestJson('GET','resources/applicants/'.rawurlencode($applicantId).'/one');
    }
    public function getApplicantStatus(string $applicantId): array {
        return $this->requestJson('GET','resources/applicants/'.rawurlencode($applicantId).'/status');
    }
    public function getApplicantByExternalUserId(string $externalUserId): array {
        $uri='resources/applicants/-;externalUserId='.rawurlencode($externalUserId).'/one';
        return $this->requestJson('GET',$uri);
    }
    public function getApplicantVerifications(string $applicantId): array {
        return $this->requestJson('GET','resources/applicants/'.rawurlencode($applicantId).'/verifications');
    }

    private function requestJson(string $method,string $uri,array $options=[]): array{
        try{ $res=$this->http->request($method,ltrim($uri,'/'),$options); }
        catch(\GuzzleHttp\Exception\GuzzleException $e){ throw new SumsubApiException(0,null,'Transport error: '.$e->getMessage()); }
        $status=$res->getStatusCode(); $body=(string)$res->getBody(); $json= $body!='' ? json_decode($body, true): null;
        if ($status<200 || $status>=300){
            $msg=(is_array($json)&&isset($json['message'])?$json['message']:'Sumsub error')+"";
            $msg.=" [{$method} /"+ltrim($uri,'/')+"]";
            throw new SumsubApiException($status, is_array($json)?$json:null, $msg);
        }
        return is_array($json)?$json:[];
    }
}