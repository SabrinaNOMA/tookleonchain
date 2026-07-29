<?php
declare(strict_types=1);

final class KycAmlRepo
{
    private \PDO $pdo;

    public function __construct(array $db)
    {
        $this->pdo = new \PDO(
            $db['dsn'], $db['user'], $db['pass'],
            [\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE=>\PDO::FETCH_ASSOC]
        );
    }

    public function upsertKycStatus(string $applicantId, ?string $externalUserId, ?array $statusJson, ?array $applicantJson): void
    {
        $reviewStatus = $statusJson['reviewStatus'] ?? null;
        $reviewAnswer = $statusJson['reviewAnswer'] ?? null;
        $rejectLabels = isset($statusJson['rejectLabels']) ? json_encode($statusJson['rejectLabels'],
            JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null;
        $moderation   = $statusJson['moderationComment'] ?? null;
        $createdAt    = $applicantJson['createdAt'] ?? null;
        $reviewedAt   = $statusJson['reviewedAt'] ?? null;

        $sql = <<<SQL
INSERT INTO kyc_applicants
 (applicant_id, external_user_id, review_status, review_answer, reject_labels,
  moderation_comment, created_at, reviewed_at, raw_status, raw_applicant)
VALUES
 (:applicant_id,:external_user_id,:review_status,:review_answer,:reject_labels,
  :moderation_comment,:created_at,:reviewed_at,:raw_status,:raw_applicant)
ON DUPLICATE KEY UPDATE
 external_user_id=VALUES(external_user_id),
 review_status=VALUES(review_status),
 review_answer=VALUES(review_answer),
 reject_labels=VALUES(reject_labels),
 moderation_comment=VALUES(moderation_comment),
 created_at=VALUES(created_at),
 reviewed_at=VALUES(reviewed_at),
 raw_status=VALUES(raw_status),
 raw_applicant=VALUES(raw_applicant)
SQL;
        $st = $this->pdo->prepare($sql);
        $st->execute([
            ':applicant_id'      => $applicantId,
            ':external_user_id'  => $externalUserId,
            ':review_status'     => $reviewStatus,
            ':review_answer'     => $reviewAnswer,
            ':reject_labels'     => $rejectLabels,
            ':moderation_comment'=> $moderation,
            ':created_at'        => $createdAt,
            ':reviewed_at'       => $reviewedAt,
            ':raw_status'        => $statusJson ? json_encode($statusJson, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null,
            ':raw_applicant'     => $applicantJson ? json_encode($applicantJson, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null,
        ]);
    }

    public function upsertAmlStatus(string $applicantId, ?array $verifs): void
    {
        $pick = function(?array $verifs, string $type): ?string {
            if (!$verifs) return null;
            foreach ($verifs as $v) {
                if (isset($v['checkType']) && strtoupper((string)$v['checkType']) === $type) {
                    return isset($v['result']) ? (string)$v['result'] : null;
                }
            }
            return null;
        };
        $san   = $pick($verifs, 'SANCTIONS') ?? null;
        $pep   = $pick($verifs, 'PEP') ?? null;
        $adv   = $pick($verifs, 'ADVERSE_MEDIA') ?? null;
        $ts    = null;
        if ($verifs) {
            foreach ($verifs as $v) $ts = $v['checkedAt'] ?? ($v['createdAt'] ?? $ts);
        }

        $sql = <<<SQL
INSERT INTO kyc_aml
 (applicant_id, sanctions_result, pep_result, adverse_result, checked_at, raw_verifications)
VALUES
 (:applicant_id,:sanctions_result,:pep_result,:adverse_result,:checked_at,:raw_verifications)
ON DUPLICATE KEY UPDATE
 sanctions_result=VALUES(sanctions_result),
 pep_result=VALUES(pep_result),
 adverse_result=VALUES(adverse_result),
 checked_at=VALUES(checked_at),
 raw_verifications=VALUES(raw_verifications)
SQL;
        $st = $this->pdo->prepare($sql);
        $st->execute([
            ':applicant_id'      => $applicantId,
            ':sanctions_result'  => $san,
            ':pep_result'        => $pep,
            ':adverse_result'    => $adv,
            ':checked_at'        => $ts,
            ':raw_verifications' => $verifs ? json_encode($verifs, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null,
        ]);
    }
}