<?php
/**
 * Terms of Service Page
 * Publicly accessible legal document.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Logic for links removed as header is removed
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service - Tookle</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Montserrat', sans-serif; }
        /* Typography overrides for legal text readability */
        .prose h1 { color: #111827; font-weight: 700; margin-bottom: 1.5rem; }
        .prose h2 { color: #374151; font-weight: 600; margin-top: 2rem; margin-bottom: 1rem; font-size: 1.25rem; }
        .prose h3 { color: #4b5563; font-weight: 600; margin-top: 1.5rem; margin-bottom: 0.75rem; font-size: 1.1rem; }
        .prose p { color: #4b5563; line-height: 1.6; margin-bottom: 1rem; }
        .prose ul { list-style-type: disc; padding-left: 1.5rem; margin-bottom: 1rem; color: #4b5563; }
        .prose li { margin-bottom: 0.5rem; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800">

    <main class="max-w-4xl mx-auto px-6 py-12">
        <div class="bg-white shadow-sm rounded-xl p-8 md:p-12 border border-gray-100">
            <article class="prose max-w-none">
                <h1 class="text-3xl tracking-tight uppercase">TERMS OF Services</h1>
                
                <p class="lead text-gray-600 mb-8 border-l-4 border-indigo-500 pl-4 bg-gray-50 py-2">
                    These Terms of Services (“Terms”) govern your access to and use of Tookle’s technical infrastructure when participating in a private fundraising campaign initiated by a Founder. By accessing a Tookle deal room or contributing to a project hosted through the Tookle interface, you agree to be bound by these Terms.
                </p>

                <h2>1. Nature of Tookle’s Role</h2>
                <h3>1.1. Infrastructure Provider Only</h3>
                <p>Tookle is a technical software platform used by Founders to configure and operate private, non-public fundraising campaigns. Tookle provides an interface, on-chain automation tools, and workflow logic.</p>
                <p>Tookle does not:</p>
                <ul class="list-disc pl-5">
                    <li>hold, receive, safeguard, or manage user funds or digital assets;</li>
                    <li>provide investment, financial, or legal advice;</li>
                    <li>act as a broker, intermediary, custodian, trustee, fiduciary, agent, or escrow provider.</li>
                </ul>

                <h3>1.2. Backer Status and Dual Relationship</h3>
                <p>By participating in a campaign hosted through Tookle, you engage in two fully independent relationships:</p>
                <div class="pl-4 border-l-2 border-gray-200 mb-4">
                    <strong>(a) Relationship with Tookle</strong><br>
                    You are a user of Tookle’s technical infrastructure. You may be required to pay protocol and network transaction fees for use of the platform, as further described in Section 2.7. This usage relationship involves no custody, no fund handling, and no financial intermediation by Tookle.
                </div>
                <div class="pl-4 border-l-2 border-gray-200">
                    <strong>(b) Relationship with the Founder</strong><br>
                    Your contribution, contractual rights, and the delivery of tokens or digital benefits relate exclusively to your agreement with the Founder. Tookle is not a party to that agreement and assumes no responsibility or liability for its execution, legality, performance, or outcome.
                </div>

                <h2>2. Custody and Security</h2>
                <h3>2.1. Non-Custodial Infrastructure</h3>
                <p>Tookle never controls, stores, transmits, or supervises funds or digital assets. All contributions and transfers occur directly between your wallet and blockchain addresses configured and controlled by the Founder.</p>

                <h3>2.2. Smart-Contract Controlled Setup</h3>
                <p>When contributing to a project, your funds are transferred directly to a programmed smart wallet safe (“Smart Wallet Safe”) deployed and configured by the Founder.</p>
                <p>The Smart Wallet Safe:</p>
                <ul>
                    <li>is controlled solely by the Founder;</li>
                    <li>operates according to rules encoded by the Founder;</li>
                    <li>is not operated, modified, accessed, or controlled by Tookle.</li>
                </ul>

                <h3>2.3. No Access or Control by Tookle</h3>
                <p>Tookle:</p>
                <ul>
                    <li>has no private keys;</li>
                    <li>cannot sign or authorize transactions;</li>
                    <li>cannot approve, block, freeze, or redirect transfers;</li>
                    <li>cannot modify wallet permissions or smart-contract logic.</li>
                </ul>
                <p>Tookle has zero technical ability to move, recover, unlock, withdraw, or supervise funds.</p>

                <h3>2.4. On-Chain Transfer Logic Defined by the Founder</h3>
                <p>Any outgoing transfer from the Smart Wallet Safe follows the on-chain logic configured exclusively by the Founder. This logic may include:</p>
                <ul>
                    <li>time-based conditions,</li>
                    <li>programmed thresholds.</li>
                </ul>
                <p>Tookle does not validate, enforce, or monitor these conditions.</p>

                <h3>2.5. Technical Withdrawal Mechanism</h3>
                <p>If the Founder has enabled a withdrawal function in the Smart Wallet or associated smart contracts, Backers may retrieve their contribution directly on-chain under the conditions encoded by the Founder (e.g., timeouts, minimum raise thresholds, or unmet deployment conditions).</p>
                <p>Tookle does not:</p>
                <ul>
                    <li>manage withdrawal requests,</li>
                    <li>process reimbursements,</li>
                    <li>approve or deny any withdrawal,</li>
                    <li>guarantee the availability or correctness of this mechanism.</li>
                </ul>

                <h3>2.6. Transparency</h3>
                <p>The Smart Wallet Safe address and its configuration are public and verifiable on-chain. Backers are responsible for reviewing this information before contributing.</p>

                <h3>2.7. Protocol Fees and Network Costs</h3>
                <p>Certain actions performed through the Tookle technical infrastructure, including but not limited to the claiming or execution of rights via smart contracts, are subject to fees.</p>
                <p>Specifically:</p>
                <div class="pl-4 border-l-2 border-gray-200 mb-4">
                    <strong>(a) Protocol Fee</strong><br>
                    A fixed protocol usage fee of USD 1 (one US dollar) per claim is charged at the time the relevant smart-contract function is executed. This fee:
                    <ul class="list-none pl-0 mt-2 text-sm">
                        <li>– applies per claim;</li>
                        <li>– is automatically enforced on-chain by the smart contract;</li>
                        <li>– is non-refundable once executed.</li>
                    </ul>
                </div>
                <div class="pl-4 border-l-2 border-gray-200 mb-4">
                    <strong>(b) Blockchain Network Fees (Gas Fees)</strong><br>
                    In addition to the protocol fee, users are required to pay blockchain network transaction fees (“gas fees”), the amount of which:
                    <ul class="list-none pl-0 mt-2 text-sm">
                        <li>– is determined solely by the underlying blockchain network;</li>
                        <li>– may vary based on network conditions;</li>
                        <li>– is not controlled, set, collected, or received by Tookle.</li>
                    </ul>
                </div>
                <div class="pl-4 border-l-2 border-gray-200 mb-4">
                    <strong>(c) No Discretion or Manual Intervention</strong><br>
                    All applicable fees are executed automatically by the smart contract. Tookle does not:
                    <ul class="list-none pl-0 mt-2 text-sm">
                        <li>– calculate fees on behalf of users;</li>
                        <li>– manually charge or invoice fees;</li>
                        <li>– refund, rebate, or adjust fees.</li>
                    </ul>
                </div>
                <div class="pl-4 border-l-2 border-gray-200">
                    <strong>(d) Smart Contract Supremacy</strong><br>
                    Users acknowledge that the economic conditions encoded in the deployed smart contracts govern transaction execution. In the event of any inconsistency between these Terms and the smart-contract logic, the smart contract shall prevail.
                </div>

                <h2>3. Disclaimers and Risk Acknowledgement</h2>
                <h3>3.1. High-Risk Digital Contribution</h3>
                <p>Participation in a project through Tookle involves significant risks, including:</p>
                <ul>
                    <li>complete loss of contributed funds,</li>
                    <li>token volatility,</li>
                    <li>smart-contract vulnerabilities,</li>
                    <li>incorrect configurations by the Founder,</li>
                    <li>Founder failure, operational errors, or regulatory changes.</li>
                </ul>
                <p>You acknowledge that:</p>
                <ul>
                    <li>Tookle does not vet Founders;</li>
                    <li>Tookle does not verify project claims;</li>
                    <li>Tookle does not perform due diligence;</li>
                    <li>Tookle does not endorse, validate, or approve any project;</li>
                    <li>Blockchain transactions are irreversible, and once executed, claims and associated fees cannot be reversed or recovered.</li>
                </ul>

                <h3>3.2. No Endorsement</h3>
                <p>The presence of a project on Tookle:</p>
                <ul>
                    <li>does not constitute a recommendation;</li>
                    <li>does not imply legitimacy, reliability, or feasibility;</li>
                    <li>is not an evaluation of legal, technical, tax, or financial soundness.</li>
                </ul>

                <h3>3.3. Compliance Firewall (Private Access Only)</h3>
                <p>By entering a Tookle deal room, you acknowledge and agree that:</p>
                <blockquote class="italic text-gray-600 border-l-4 border-gray-300 pl-4 py-2 bg-gray-50 my-4">
                    “This campaign is private, non-public, and managed directly by its Founder. Tookle does not intermediate, promote, or distribute any offers.”
                </blockquote>
                <p>Nothing on the Tookle platform constitutes a public offer, solicitation, or distribution of securities or crypto-assets by Tookle. You are solely responsible for ensuring that your participation is legal in your jurisdiction.</p>

                <h3>3.4. No Financial, Legal, or Investment Advice</h3>
                <p>Nothing provided through Tookle—whether user interfaces, documentation, analytics, communications, automated outputs, or project listings—constitutes or should be interpreted as:</p>
                <ul>
                    <li>financial advice,</li>
                    <li>investment advice,</li>
                    <li>legal advice,</li>
                    <li>tax advice,</li>
                    <li>any other form of professional advice.</li>
                </ul>
                <p>You acknowledge that:</p>
                <ul>
                    <li>Tookle does not assess suitability or appropriateness;</li>
                    <li>Tookle does not recommend contributing to any project;</li>
                    <li>All decisions you take are your sole responsibility, based on your own independent judgment or professional advisors.</li>
                </ul>

                <h2>4. Liability Exclusion</h2>
                <p>To the maximum extent permitted by Swiss law, Tookle shall not be liable for:</p>
                <ul>
                    <li>project failure, Founder misconduct, insolvency, or fraud;</li>
                    <li>any loss of funds or digital assets;</li>
                    <li>any smart-contract error, exploit, or technical malfunction;</li>
                    <li>blockchain or network outages;</li>
                    <li>misconfiguration of the Smart Wallet Safe;</li>
                    <li>incorrect wallet addresses provided by Backers;</li>
                    <li>delayed or failed token delivery;</li>
                    <li>changes to token utility or functionality;</li>
                    <li>regulatory, tax, or legal consequences arising from your participation.</li>
                </ul>
                <p>Tookle does not guarantee:</p>
                <ul>
                    <li>the success of any project;</li>
                    <li>the delivery of any digital asset;</li>
                    <li>the value, utility, or future performance of any token;</li>
                    <li>that a Token Generation Event will occur.</li>
                </ul>

                <h2>5. Governing Law and Jurisdiction</h2>
                <p>These Terms are governed exclusively by the laws of Switzerland. Any dispute arising from these Terms or your use of Tookle shall be subject to the exclusive jurisdiction of the competent courts of Lausanne, Switzerland.</p>

                <h2>6. Acceptance</h2>
                <p>By accessing Tookle or contributing to a project through the Tookle interface, you acknowledge that you have read, understood, and agreed to be bound by these Terms.</p>

            </article>
        </div>

        <footer class="mt-12 text-center text-sm text-gray-400">
            &copy; <?php echo date('Y'); ?> Tookle. All rights reserved. <a href="<?= get_url('privacy') ?>" class="hover:text-gray-600 ml-2">Privacy Policy</a>
        </footer>
    </main>
</body>
</html>