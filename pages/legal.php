<?php
/**
 * Investor Legal, Compliance & Commercial Records Portal
 * Uses Tailwind CSS & Tookle Design System
 */
if (!isset($page_data)) {
    $page_data = [];
}
$tsa_list = $page_data['tsa_list'] ?? [];
$commercial_records = $page_data['commercial_records'] ?? [];
$user_info = $page_data['user_info'] ?? [];
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    
    <!-- Page Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Regulatory & Commercial Records</h1>
        <p class="text-gray-600 mt-1">
            Access your Token Sale Agreements (TSAs), review statutory utility classifications under EU MiCA and Swiss FINMA, and download commercial bookkeeping records for your accounting.
        </p>
    </div>

    <!-- Portal Tabs -->
    <div class="flex border-b border-gray-200 mb-8 space-x-8">
        <button class="portal-tab-btn active pb-4 px-2 font-bold text-sm border-b-2 border-gray-900 text-gray-900 transition-colors flex items-center gap-2" data-target="tab-regulatory">
            <i data-lucide="scale" class="w-4 h-4"></i>
            <span>Legal & Regulatory Framework</span>
        </button>
        <button class="portal-tab-btn pb-4 px-2 font-bold text-sm border-b-2 border-transparent text-gray-500 hover:text-gray-700 transition-colors flex items-center gap-2" data-target="tab-commercial">
            <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>
            <span>Commercial Transaction Record</span>
        </button>
    </div>

    <!-- TAB 1: LEGAL & REGULATORY FRAMEWORK -->
    <div id="tab-regulatory" class="portal-tab-content space-y-8">
        
        <!-- SECTION 1: My Token Sale Agreements (TSAs) -->
        <div class="bg-white rounded-2xl p-6 sm:p-8 border border-gray-100 shadow-sm">
            <div class="flex items-start justify-between mb-6">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">My Token Sale Agreements (TSAs)</h2>
                    <p class="text-sm text-gray-500 mt-1">Download the legally binding agreements signed between you and the respective project creators.</p>
                </div>
                <div class="p-2 bg-gray-100 text-gray-900 rounded-lg">
                    <i data-lucide="file-check" class="w-6 h-6"></i>
                </div>
            </div>

            <?php if (empty($tsa_list)): ?>
                <div class="p-6 bg-gray-50 rounded-xl text-center border border-dashed border-gray-300">
                    <p class="text-sm text-gray-500">No signed Token Sale Agreements found in your portfolio yet.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($tsa_list as $tsa): ?>
                        <div class="p-4 border border-gray-200 rounded-xl hover:border-gray-300 hover:bg-gray-50/50 transition-all flex items-center justify-between">
                            <div class="space-y-1">
                                <h4 class="font-bold text-gray-900 text-sm"><?php echo htmlspecialchars($tsa['project_name']); ?></h4>
                                <div class="flex items-center gap-2 text-xs text-gray-500">
                                    <span class="font-medium"><?php echo htmlspecialchars($tsa['round']); ?></span>
                                    <span>•</span>
                                    <span><?php echo $tsa['date']; ?></span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <?php if ($tsa['tsa_url'] && $tsa['tsa_url'] !== '#'): ?>
                                    <a href="<?php echo htmlspecialchars($tsa['tsa_url']); ?>" target="_blank" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-gray-900 hover:bg-gray-800 text-white text-xs font-semibold rounded-lg transition-colors shadow-sm">
                                        <i data-lucide="download" class="w-3.5 h-3.5"></i>
                                        <span>Download PDF</span>
                                    </a>
                                <?php endif; ?>
                                <?php if ($tsa['has_snapshot']): ?>
                                    <script id="tsa-snap-<?php echo $tsa['id']; ?>" type="application/json"><?php echo json_encode($tsa['snapshot_html']); ?></script>
                                    <button onclick="openTsaModal(<?php echo $tsa['id']; ?>, '<?php echo htmlspecialchars(addslashes($tsa['project_name'])); ?>')" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-gray-900 hover:bg-gray-800 text-white text-xs font-semibold rounded-lg transition-colors shadow-sm">
                                        <i data-lucide="file-text" class="w-3.5 h-3.5"></i>
                                        <span>View Signed Contract</span>
                                    </button>
                                <?php elseif (!$tsa['tsa_url'] || $tsa['tsa_url'] === '#'): ?>
                                    <span class="text-xs text-gray-400 italic">Agreement pending signature</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- SECTION 2: Tokenisation Overview & Value Accrual -->
        <div class="bg-white rounded-2xl p-6 sm:p-8 border border-gray-100 shadow-sm space-y-6">
            <div>
                <h2 class="text-xl font-bold text-gray-900">Utility Tokenisation & Value Accrual</h2>
                <p class="text-sm text-gray-500 mt-1">Understanding the economic and legal mechanics of digital utility tokens.</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="p-5 bg-gray-50 rounded-xl border border-gray-200">
                    <h3 class="font-bold text-gray-900 mb-2 flex items-center gap-2 text-sm">
                        <i data-lucide="layers" class="w-4 h-4 text-gray-900"></i>
                        <span>Utility Tokenisation</span>
                    </h3>
                    <p class="text-xs text-gray-600 leading-relaxed">
                        Utility tokenisation embeds digital access rights, software licenses, or prepaid service allocations into a blockchain-based token. These tokens do not grant equity ownership in the issuing company, voting rights, or any entitlement to corporate dividends.
                    </p>
                </div>

                <div class="p-5 bg-gray-50 rounded-xl border border-gray-200">
                    <h3 class="font-bold text-gray-900 mb-2 flex items-center gap-2 text-sm">
                        <i data-lucide="trending-up" class="w-4 h-4 text-gray-900"></i>
                        <span>Non-Security Value Accrual</span>
                    </h3>
                    <p class="text-xs text-gray-600 leading-relaxed">
                        Value accrual refers to economic mechanisms that tie the token’s market utility to supply-demand mechanics without crossing the regulatory threshold into a financial security.
                    </p>
                </div>
            </div>

            <!-- Value Accrual Mechanisms Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 pt-2">
                <div class="p-4 rounded-xl border border-gray-100 bg-gray-50/50">
                    <div class="font-bold text-xs text-gray-900 mb-1">Medium of Exchange</div>
                    <p class="text-[11px] text-gray-500 leading-relaxed">Required to pay for protocol network fees, subscriptions, or decentralized services.</p>
                </div>
                <div class="p-4 rounded-xl border border-gray-100 bg-gray-50/50">
                    <div class="font-bold text-xs text-gray-900 mb-1">Sinking & Burning</div>
                    <p class="text-[11px] text-gray-500 leading-relaxed">A portion of tokens utilized within the application is permanently removed from circulation.</p>
                </div>
                <div class="p-4 rounded-xl border border-gray-100 bg-gray-50/50">
                    <div class="font-bold text-xs text-gray-900 mb-1">Tiered Staking</div>
                    <p class="text-[11px] text-gray-500 leading-relaxed">Users lock up tokens to unlock institutional tiers, premium features, or enhanced API limits.</p>
                </div>
                <div class="p-4 rounded-xl border border-gray-100 bg-gray-50/50">
                    <div class="font-bold text-xs text-gray-900 mb-1">Discount Models</div>
                    <p class="text-[11px] text-gray-500 leading-relaxed">Paying for platform services with utility tokens grants substantial commercial fee discounts.</p>
                </div>
            </div>
        </div>

        <!-- SECTION 3: Statutory Token Classifications (MiCA & FINMA) -->
        <div class="bg-white rounded-2xl p-6 sm:p-8 border border-gray-100 shadow-sm space-y-6">
            <div>
                <h2 class="text-xl font-bold text-gray-900">Statutory Token Classifications</h2>
                <p class="text-sm text-gray-500 mt-1">Regulatory definitions governing utility tokens in the European Union and Switzerland.</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- MiCA (EU) -->
                <div class="p-6 rounded-2xl border border-gray-200 bg-white space-y-4 shadow-sm">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-4">
                        <div>
                            <span class="text-[10px] font-extrabold uppercase tracking-widest text-gray-700 bg-gray-100 px-2.5 py-1 rounded-full border border-gray-200">European Union</span>
                            <h3 class="font-bold text-gray-900 text-lg mt-2">MiCA Regulation (EU) 2023/1114</h3>
                        </div>
                        <i data-lucide="globe" class="w-6 h-6 text-gray-400"></i>
                    </div>

                    <div class="space-y-3 text-xs">
                        <div class="p-3 bg-gray-50 rounded-lg border-l-2 border-gray-800">
                            <strong class="text-gray-900 block mb-1">Utility Token (Article 3)</strong>
                            <p class="text-gray-600 italic">"a type of crypto-asset that is only intended to provide access to a good or a service supplied by its issuer"</p>
                        </div>
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <strong class="text-gray-900 block mb-1">Crypto-asset</strong>
                            <p class="text-gray-600 italic">"a digital representation of a value or of a right that is able to be transferred and stored electronically using distributed ledger technology or similar technology"</p>
                        </div>
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <strong class="text-gray-900 block mb-1">Asset-referenced & E-money Tokens</strong>
                            <p class="text-gray-600 italic">Tokens referencing external fiat currencies or physical commodities to maintain stable monetary value.</p>
                        </div>
                    </div>
                </div>

                <!-- FINMA (Switzerland) -->
                <div class="p-6 rounded-2xl border border-gray-200 bg-white space-y-4 shadow-sm">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-4">
                        <div>
                            <span class="text-[10px] font-extrabold uppercase tracking-widest text-gray-700 bg-gray-100 px-2.5 py-1 rounded-full border border-gray-200">Switzerland</span>
                            <h3 class="font-bold text-gray-900 text-lg mt-2">FINMA ICO Guidelines (2018)</h3>
                        </div>
                        <i data-lucide="shield" class="w-6 h-6 text-gray-400"></i>
                    </div>

                    <div class="space-y-3 text-xs">
                        <div class="p-3 bg-gray-50 rounded-lg border-l-2 border-gray-800">
                            <strong class="text-gray-900 block mb-1">Utility Tokens (Category 2)</strong>
                            <p class="text-gray-600 italic">"Utility tokens are tokens which are intended to provide digital access to an application or service."</p>
                        </div>
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <strong class="text-gray-900 block mb-1">Payment Tokens (Category 1)</strong>
                            <p class="text-gray-600 italic">"Payment tokens are synonymous with cryptocurrencies and have no further functions or links to other development projects."</p>
                        </div>
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <strong class="text-gray-900 block mb-1">Asset Tokens (Category 3 - Excluded)</strong>
                            <p class="text-gray-600 italic">"Asset tokens represent assets such as participations in real physical underlyings, companies, or earnings streams... analogous to equities, bonds or derivatives."</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 4: TOOKLE & GENERAL RISK DISCLAIMERS -->
        <div class="space-y-4">
            <!-- TOOKLE Platform Disclaimer -->
            <div class="p-6 rounded-2xl bg-gray-900 text-gray-200 border border-gray-800 shadow-sm">
                <div class="flex items-start gap-4">
                    <div class="p-2 bg-gray-800 rounded-lg text-gray-300 shrink-0">
                        <i data-lucide="info" class="w-6 h-6"></i>
                    </div>
                    <div class="space-y-2">
                        <h4 class="font-bold text-white text-sm uppercase tracking-wider">TOOKLE Platform & Non-Custodial Notice</h4>
                        <p class="text-xs text-gray-300 leading-relaxed">
                            TOOKLE is a decentralized software technology provider and interface. TOOKLE does not act as an issuer, broker-dealer, investment advisor, exchange, or custodian of user funds, private keys, or digital assets at any time. All transactions are executed directly on-chain between contributors and independent project creators via non-custodial smart contracts. <strong>TOOKLE is not a party to any Token Sale Agreement (TSA)</strong> and assumes no legal or financial liability for the performance, delivery, or regulatory classification of third-party tokens.
                        </p>
                    </div>
                </div>
            </div>

            <!-- General Legal Disclaimer -->
            <div class="p-6 rounded-2xl bg-amber-50/70 border border-amber-200/80 text-amber-950 shadow-sm">
                <div class="flex items-start gap-4">
                    <div class="p-2 bg-amber-100/80 rounded-lg text-amber-700 shrink-0">
                        <i data-lucide="alert-triangle" class="w-6 h-6"></i>
                    </div>
                    <div class="space-y-2">
                        <h4 class="font-bold text-amber-900 text-sm uppercase tracking-wider">Important Legal & Regulatory Disclaimer</h4>
                        <p class="text-xs text-amber-900/90 leading-relaxed">
                            This information is for educational and compliance purposes only and does not constitute financial, investment, legal, or tax advice. The regulatory status of cryptographic tokens is unsettled and varies significantly across jurisdictions. By engaging in any token sale, you participate at your own risk and are responsible for complying with the statutory laws in your country of residence. <strong>Before making any decision to acquire, hold, or utilize digital tokens, you must consult with a qualified legal and accounting professional in your jurisdiction.</strong>
                        </p>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- TAB 2: COMMERCIAL TRANSACTION RECORD -->
    <div id="tab-commercial" class="portal-tab-content hidden space-y-8">
        
        <!-- Printable Accounting Document Container -->
        <div id="printable-commercial-statement" class="bg-white rounded-2xl p-8 sm:p-10 border border-gray-200 shadow-sm space-y-8">
            
            <!-- Document Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-gray-200 pb-6">
                <div>
                    <div class="text-[10px] font-extrabold uppercase tracking-widest text-gray-500 mb-1">Commercial Accounting Record</div>
                    <h2 class="text-2xl font-black text-gray-900">Commercial Contribution & Utility Access Statement</h2>
                    <p class="text-xs text-gray-500 mt-1">Statement Date: <?php echo date('F d, Y'); ?> • Non-Security Utility Classification</p>
                </div>
                <div class="flex items-center gap-3">
                    <button onclick="window.print()" class="inline-flex items-center gap-2 px-4 py-2.5 bg-gray-900 hover:bg-gray-800 text-white font-bold text-xs rounded-xl shadow-sm transition-colors print:hidden">
                        <i data-lucide="printer" class="w-4 h-4"></i>
                        <span>Print / Save as PDF</span>
                    </button>
                </div>
            </div>

            <!-- Contributor & Classification Notice -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 p-5 bg-gray-50 rounded-xl border border-gray-200 text-xs">
                <div>
                    <span class="font-bold text-gray-500 uppercase text-[10px] block mb-1">Contributor Account</span>
                    <div class="font-bold text-gray-900 text-sm">
                        <?php echo htmlspecialchars(($user_info['first_name'] ?? '') . ' ' . ($user_info['last_name'] ?? 'Investor')); ?>
                    </div>
                    <div class="text-gray-600"><?php echo htmlspecialchars($user_info['email'] ?? ''); ?></div>
                    <div class="text-gray-800 font-semibold mt-1">KYC Status: <?php echo strtoupper(htmlspecialchars($user_info['kyc_status'] ?? 'VERIFIED')); ?></div>
                </div>
                <div>
                    <span class="font-bold text-gray-500 uppercase text-[10px] block mb-1">Statutory Asset Classification</span>
                    <div class="font-bold text-gray-900">Utility Access Right (Non-Equity)</div>
                    <p class="text-gray-600 mt-1">
                        Tokens listed represent prepaid commercial software licenses and platform access rights. They confer zero equity, voting rights, dividend entitlements, or debt claims.
                    </p>
                </div>
            </div>

            <!-- Commercial Transactions Table -->
            <div>
                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider mb-4">Historical Contribution Ledger</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b-2 border-gray-200 text-[10px] font-extrabold text-gray-500 uppercase tracking-wider">
                                <th class="py-3 px-2">Project & Round</th>
                                <th class="py-3 px-2">Date</th>
                                <th class="py-3 px-2">Transaction Type</th>
                                <th class="py-3 px-2">Asset Class</th>
                                <th class="py-3 px-2 text-right">Contribution (USD)</th>
                                <th class="py-3 px-2 text-right">Token Allocation</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-xs">
                            <?php if (empty($commercial_records)): ?>
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-gray-400">No commercial transactions recorded yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($commercial_records as $rec): ?>
                                    <tr class="hover:bg-gray-50/50">
                                        <td class="py-4 px-2 font-bold text-gray-900">
                                            <?php echo htmlspecialchars($rec['project_name']); ?>
                                            <span class="block text-[10px] font-normal text-gray-500"><?php echo htmlspecialchars($rec['round']); ?></span>
                                        </td>
                                        <td class="py-4 px-2 text-gray-600"><?php echo $rec['date']; ?></td>
                                        <td class="py-4 px-2">
                                            <span class="px-2 py-0.5 rounded bg-gray-100 text-gray-700 font-medium text-[11px]">Commercial Contribution</span>
                                        </td>
                                        <td class="py-4 px-2 text-gray-600"><?php echo htmlspecialchars($rec['asset_class']); ?></td>
                                        <td class="py-4 px-2 text-right font-mono font-bold text-gray-900">$<?php echo number_format($rec['amount_usd'], 2); ?></td>
                                        <td class="py-4 px-2 text-right font-mono font-black text-gray-900"><?php echo number_format($rec['token_quantity'], 0); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Legally Protective Footer / Technology Disclaimer -->
            <div class="border-t border-gray-200 pt-6 text-[11px] text-gray-500 space-y-2">
                <p>
                    <strong>Statement Purpose:</strong> This document is generated for commercial bookkeeping and accounting purposes. It confirms the delivery or pending allocation of digital utility access rights under standard commercial Token Sale Agreements.
                </p>
                <p>
                    <strong>TOOKLE Non-Custodial & Non-Party Disclaimer:</strong> TOOKLE is a decentralized software infrastructure provider and user interface. TOOKLE does not act as an issuer, broker, dealer, financial advisor, or custodian of user funds or digital assets. All transactions listed above were executed directly on-chain between the contributor and independent project creators. TOOKLE is not a party to any Token Sale Agreement and bears no legal or financial liability for third-party token performance.
                </p>
            </div>

        </div>

    </div>

</div>

<!-- Printable CSS Styling -->
<style>
@media print {
    body * {
        visibility: hidden;
    }
    #printable-commercial-statement, #printable-commercial-statement * {
        visibility: visible;
    }
    #printable-commercial-statement {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
    }
}
</style>

<!-- Tab Switching Script -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    const tabBtns = document.querySelectorAll('.portal-tab-btn');
    const tabContents = document.querySelectorAll('.portal-tab-content');

    tabBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            tabBtns.forEach(b => {
                b.classList.remove('active', 'border-gray-900', 'text-gray-900');
                b.classList.add('border-transparent', 'text-gray-500');
            });

            const targetBtn = e.currentTarget;
            targetBtn.classList.remove('border-transparent', 'text-gray-500');
            targetBtn.classList.add('active', 'border-gray-900', 'text-gray-900');

            const targetId = targetBtn.dataset.target;
            tabContents.forEach(content => content.classList.add('hidden'));
            const targetEl = document.getElementById(targetId);
            if (targetEl) {
                targetEl.classList.remove('hidden');
            }
        });
    });
});

function openTsaModal(id, title) {
    const scriptEl = document.getElementById('tsa-snap-' + id);
    if (!scriptEl) return;
    try {
        const htmlContent = JSON.parse(scriptEl.textContent);
        document.getElementById('tsa-modal-title').textContent = title + ' - Signed Agreement';
        document.getElementById('tsa-modal-body').innerHTML = htmlContent || '<p class="text-gray-500 italic">Agreement content not available.</p>';
        document.getElementById('tsa-modal').classList.remove('hidden');
        if (typeof lucide !== 'undefined') lucide.createIcons();
    } catch (e) {
        console.error('Failed to parse agreement content:', e);
    }
}

function closeTsaModal() {
    document.getElementById('tsa-modal').classList.add('hidden');
}

function printTsaContent() {
    const modalBody = document.getElementById('tsa-modal-body').innerHTML;
    const printWin = window.open('', '_blank', 'width=800,height=900');
    printWin.document.write('<html><head><title>Print Agreement</title><style>body{font-family:sans-serif;padding:40px;line-height:1.6;}</style></head><body>' + modalBody + '</body></html>');
    printWin.document.close();
    printWin.focus();
    printWin.print();
}
</script>

<!-- Signed Contract Modal -->
<div id="tsa-modal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-4xl w-full max-h-[85vh] flex flex-col shadow-2xl border border-gray-200 overflow-hidden">
        <!-- Modal Header -->
        <div class="px-6 py-4 bg-gray-900 text-white flex items-center justify-between border-b border-gray-800">
            <div>
                <span class="text-[10px] font-extrabold uppercase tracking-widest text-gray-300 block">Token Sale Agreement (TSA)</span>
                <h3 id="tsa-modal-title" class="font-bold text-lg text-white">Project Agreement</h3>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="printTsaContent()" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-800 hover:bg-gray-700 text-white text-xs font-semibold rounded-lg transition-colors">
                    <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                    <span>Print Contract</span>
                </button>
                <button onclick="closeTsaModal()" class="p-1.5 text-gray-400 hover:text-white rounded-lg transition-colors">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
        </div>
        <!-- Modal Body -->
        <div id="tsa-modal-body" class="p-8 overflow-y-auto flex-1 prose max-w-none text-gray-800 text-sm leading-relaxed bg-white">
        </div>
    </div>
</div>
