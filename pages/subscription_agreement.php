<?php
/**
 * Subscription Agreement Page
 * Publicly accessible legal document.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Agreement - Tookle</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Montserrat', sans-serif; }
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
                <h1 class="text-3xl tracking-tight uppercase">Service Agreement</h1>
                
                <p class="lead text-gray-600 mb-8 font-medium">
                    Between:<br>
                    <strong>NOMA Sabrina Boudefar</strong>, an individual enterprise registered in Switzerland, acting under the commercial name Tookle (the “Provider”, “We”),<br>
                    and<br>
                    <strong>the Client</strong>
                </p>

                <h2>1. Purpose</h2>
                <p>Tookle provides the Client with access to a software-as-a-service (SaaS) infrastructure (“the Infrastructure”) to set up and operate private, non-public on-chain fundraising workflows.</p>
                <p>The Infrastructure includes:</p>
                <ul>
                    <li>a private deal room,</li>
                    <li>smart-contract interfaces,</li>
                    <li>on-chain automation tools,</li>
                    <li>participant management tools (“Backers”).</li>
                </ul>
                <p>Tookle’s role is strictly limited to providing technical software infrastructure. For the purposes of this Agreement, the Client may also be referred to as the ‘Founder’ when acting as the organizer of a fundraising workflow.</p>

                <h2>2. Status and Scope of Service</h2>
                
                <h3>2.1. Technology</h3>
                <p>Tookle acts exclusively as a non-custodial technology provider. Tookle does not perform:</p>
                <ul>
                    <li>financial intermediation,</li>
                    <li>brokerage or placement activity,</li>
                    <li>custody or safekeeping of assets,</li>
                    <li>payment services,</li>
                    <li>transfer services,</li>
                    <li>regulated fundraising operations,</li>
                </ul>
                <p>within the meaning of the Swiss AMLA, EU MiCA, SEC rules, EU ECSPR, or similar frameworks.</p>

                <h3>2.2. Non-Custody</h3>
                <p>Tookle does not at any time hold, control, or access funds or tokens belonging to the Founder or Backers. Funds contributed by Backers are directed exclusively to a smart-contract-controlled wallet configured and controlled by the Founder.</p>
                <p>Tookle:</p>
                <ul>
                    <li>has no private keys,</li>
                    <li>cannot sign or authorize transactions,</li>
                    <li>cannot freeze, move, or recover funds,</li>
                    <li>cannot alter on-chain conditions.</li>
                </ul>

                <h3>2.3. No Advisory, No Matching, No Brokerage</h3>
                <p>Tookle provides no:</p>
                <ul>
                    <li>financial advice,</li>
                    <li>investment advice,</li>
                    <li>legal advice,</li>
                    <li>tax advice,</li>
                    <li>fundraising strategy,</li>
                    <li>investment suitability assessment.</li>
                </ul>
                <p>Tookle does not introduce, match, or source Backers for Founders.</p>

                <h3>2.4. Private Use Only</h3>
                <p>The Infrastructure may be used only for private, non-public fundraising.</p>
                <p>The Founder must not:</p>
                <ul>
                    <li>publicly advertise or promote the deal room link,</li>
                    <li>engage in solicitation, general marketing, or public offering activity,</li>
                    <li>share access outside of private channels.</li>
                </ul>
                <p>Tookle provides the Infrastructure on a best-efforts basis and does not guarantee any fundraising outcome, performance level, or regulatory acceptance.</p>

                <h2>3. Founder’s Obligations</h2>
                
                <h3>3.1. Legal Responsibility of the Founder</h3>
                <p>The Founder carries full legal, regulatory, and operational responsibility for their fundraising activity. This includes but is not limited to:</p>
                <ul>
                    <li>legal qualification and categorization of their token or digital asset;</li>
                    <li>preparation and publication of any whitepaper, disclosure, or prospectus required by law;</li>
                    <li>ensuring that the fundraising meets private-placement requirements in all relevant jurisdictions;</li>
                    <li>conducting KYC/AML checks on all participants as required by applicable law;</li>
                    <li>ensuring that Backers meet eligibility criteria (e.g., private-only access, jurisdictional compliance).</li>
                </ul>
                <p>Tookle plays no role in these processes.</p>

                <h3>3.2. Smart Contract Configuration</h3>
                <p>The Founder is solely responsible for:</p>
                <ul>
                    <li>deploying and configuring the smart contract,</li>
                    <li>defining transfer logic, unlocking conditions, or withdrawal mechanisms,</li>
                    <li>verifying that the wallet operates as intended,</li>
                    <li>ensuring that any on-chain mechanisms comply with applicable regulations.</li>
                </ul>
                <p>Tookle does not validate, enforce, or review the correctness of these configurations.</p>

                <h3>3.3. Responsibility Transfer</h3>
                <p>All responsibilities under AMLA, MiCA, national securities laws, consumer protection, and fundraising compliance rest exclusively with the Founder.</p>
                <p>Tookle is expressly excluded from:</p>
                <ul>
                    <li>any custody duties,</li>
                    <li>any AML/KYC obligations,</li>
                    <li>any regulated financial service responsibility,</li>
                    <li>any role in fund management or investor qualification.</li>
                </ul>

                <h2>4. Fees and Access to Service</h2>
                
                <h3>4.1. Service Fees</h3>
                <ul>
                    <li><strong>Setup fee:</strong> CHF 1,000 (one-time, payable upon onboarding);</li>
                    <li><strong>Subscription fee:</strong> CHF 400 per month, payable in advance, granting continued access to the Infrastructure;</li>
                    <li><strong>Platform usage fee:</strong> 3.5% of the total amount of funds raised through the Infrastructure, payable by the Client.</li>
                </ul>
                <p>Fees are invoiced in Swiss francs (CHF). The subscription fee recurs monthly unless terminated in accordance with this Agreement.</p>
                <p>The platform usage fee constitutes remuneration for access to and use of the technical Infrastructure only.</p>
                <p>Tookle does not act as a financial intermediary, broker, placement agent, or custodian, and does not receive, hold, or control any funds contributed by Backers.</p>
                <p>The platform usage fee becomes due upon completion of the fundraising campaign or upon receipt of funds by the Founder, whichever occurs first.</p>

                <h3>4.2. Default of Payment</h3>
                <p>Failure to pay fees when due may result in suspension of access to the Infrastructure after reasonable notice. Suspension does not relieve the Founder of outstanding payment obligations.</p>

                <h3>4.3. Termination</h3>
                <p>Tookle may terminate this Agreement without prior notice if:</p>
                <ul>
                    <li>the Founder breaches these Terms,</li>
                    <li>the Founder engages in unlawful or non-compliant fundraising,</li>
                    <li>misuse of the platform exposes Tookle to regulatory or reputational risk.</li>
                </ul>

                <h2>5. Liability and Indemnification</h2>
                
                <h3>5.1. Limitation of Liability</h3>
                <p>To the maximum extent permitted by Swiss law, Tookle shall not be liable for:</p>
                <ul>
                    <li>any failed, delayed, or incomplete fundraising;</li>
                    <li>any loss of funds, digital assets, or contributions;</li>
                    <li>misconfiguration of any smart-contract-controlled wallet or smart wallet;</li>
                    <li>smart-contract vulnerabilities or failures;</li>
                    <li>blockchain or network outages;</li>
                    <li>regulatory, tax, or legal exposure resulting from the Founder’s actions;</li>
                    <li>incorrect, incomplete, or misleading information published by the Founder;</li>
                    <li>actions taken by Backers, contributors, or third parties.</li>
                </ul>

                <h3>5.2. Indemnification</h3>
                <p>The Founder shall indemnify and hold harmless Tookle, its employees, directors, and affiliates from any claims, sanctions, penalties, or damages resulting from:</p>
                <ul>
                    <li>the Founder’s regulatory or legal breaches;</li>
                    <li>violation of private placement rules;</li>
                    <li>misrepresentation to Backers;</li>
                    <li>failure to meet disclosure or compliance obligations;</li>
                    <li>improper configuration of smart-contract logic or wallets.</li>
                </ul>

                <h2>6. Assignment upon incorporation</h2>
                <p>The Provider may assign or transfer this Agreement, in whole or in part, to a company incorporated under the name Tookle SA or any successor entity, without modification of the Services or pricing, upon written notice to the Client.</p>
                <p>Such assignment shall not affect the validity of this Agreement, nor the rights and obligations of the Parties, which shall continue uninterrupted.</p>

                <h2>7. Governing Law and Jurisdiction</h2>
                <p>This Agreement is governed exclusively by the laws of Switzerland. Any dispute arising from these Terms shall be subject to the exclusive jurisdiction of the competent courts of Lausanne, Switzerland.</p>

                <h2>8. Severability</h2>
                <p>If any provision of this Agreement is held invalid or unenforceable, the remaining provisions shall remain in full force and effect.</p>



            </article>
        </div>
    </main>

</body>
</html>
