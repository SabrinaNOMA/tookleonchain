<?php
// subscription.php - Upgrade Page
// Integrated with Silicon Valley Standard Layout & Security

// Ensure CSRF token is available for the form
$csrf_token = $_SESSION['csrf_token'] ?? '';
?>

<!-- Main Container -->
<div class="bg-white text-slate-600 font-sans selection:bg-indigo-100 selection:text-indigo-900 min-h-full">
    
    <!-- Hero Section -->
    <header class="pt-16 pb-12 text-center px-4">
        <h1 class="text-4xl md:text-5xl font-extrabold text-slate-900 mb-6 tracking-tight">
            You are one click away from <span class="text-indigo-600">realizing your dream.</span>
        </h1>
        <p class="text-lg text-slate-500 max-w-2xl mx-auto leading-relaxed">
            Simple, transparent pricing. Always know what you pay.
        </p>
    </header>

    <!-- Main Pricing Section -->
    <main class="max-w-xl mx-auto px-4 pb-24">
        
        <!-- Pricing Card -->
        <div class="bg-white rounded-3xl p-1 shadow-2xl shadow-indigo-900/10 border border-slate-200">
            <div class="bg-white rounded-[22px] p-8 md:p-10 h-full flex flex-col relative overflow-hidden">
                
                <!-- Decorative background element -->
                <div class="absolute top-0 right-0 w-64 h-64 bg-indigo-50/50 blur-3xl rounded-full pointer-events-none -mr-16 -mt-16"></div>

                <div class="mb-8 relative z-10 text-center">
                    <span class="inline-block py-1.5 px-4 rounded-full bg-indigo-50 text-indigo-700 text-xs font-bold uppercase tracking-wider mb-6 border border-indigo-100">
                        Smart Fundraising Infrastructure
                    </span>
                    <div class="flex items-baseline justify-center gap-1 mb-2">
                        <span class="text-6xl font-bold text-slate-900">400</span>
                        <span class="text-2xl text-slate-500 font-medium">CHF / mo</span>
                    </div>
                    <p class="text-slate-500 text-sm">Infrastructure fee. Cancel anytime.</p>
                </div>

                <div class="space-y-6 mb-8 border-t border-b border-slate-100 py-8 relative z-10">
                    <!-- Pricing Row 1 -->
                    <div class="flex justify-between items-start mb-4 last:mb-0">
                        <div class="text-left">
                            <div class="text-slate-700 font-medium">Onboarding & Setup</div>
                            <div class="text-xs text-slate-500 mt-0.5">One-time. Dedicated environment configuration.</div>
                        </div>
                        <div class="font-bold text-lg text-slate-900">1,000 CHF</div>
                    </div>
                    
                    <!-- Platform Fee Info -->
                    <div class="flex justify-between items-start">
                        <div class="text-left">
                            <div class="text-slate-700 font-medium flex items-center gap-2">
                                Platform Fee
                                <i data-lucide="info" class="w-3.5 h-3.5 text-slate-400"></i>
                            </div>
                            <div class="text-xs text-slate-500 mt-0.5 max-w-[200px] leading-relaxed">
                                Percentage of transaction volume. 
                            </div>
                        </div>
                        <div class="font-bold text-lg text-slate-900">
                            3%
                        </div>
                    </div>
                </div>

                <!-- Features List -->
                <div class="mb-8 relative z-10">
                    <h4 class="text-sm font-semibold text-slate-900 uppercase tracking-wide mb-4 text-center">Everything included</h4>
                    <ul class="space-y-4">
                        <?php 
                        $features = [
                            "200 KYC Checks / Month",
                            "Unlimited Digital Asset Creation",
                            "Live Cap Table",
                            "Backer Dashboard",
                            "Private Deal Rooms",
                            "Smart Vault Storage",
                            "Global Distribution"
                        ];
                        foreach($features as $feature): ?>
                        <li class="flex items-center gap-3 text-slate-700">
                            <div class="flex-shrink-0 w-6 h-6 rounded-full bg-indigo-50 flex items-center justify-center">
                                <i data-lucide="check-circle-2" class="w-4 h-4 text-indigo-600"></i>
                            </div>
                            <span class="text-sm font-medium"><?php echo $feature; ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Form Start -->
                <!-- UPDATED: Action removed to handle via JS redirect -->
                <form id="subscription-form" onsubmit="event.preventDefault(); window.location.href='https://buy.stripe.com/9B614mh2A0MTaU561o4c801';">
                    <!-- Security Token (Not strictly needed for redirect but good practice if logic changes) -->
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    
                    <!-- Terms & Conditions Checkbox -->
                    <div class="mb-6 relative z-10 bg-slate-50 rounded-lg p-4 border border-slate-100">
                        <label class="flex items-start gap-3 cursor-pointer group">
                            <div class="flex items-center h-5">
                                <input 
                                    type="checkbox" 
                                    id="terms-checkbox"
                                    class="w-4 h-4 text-indigo-600 border-slate-300 rounded focus:ring-indigo-500 cursor-pointer"
                                >
                            </div>
                            <div class="text-sm text-slate-600 group-hover:text-slate-900 transition-colors">
                                I agree to the <a href="/pages/terms.php" target="_blank" class="text-indigo-600 font-semibold hover:underline">Terms of Service</a> and <a href="/pages/subscription_agreement.php" target="_blank" class="text-indigo-600 font-semibold hover:underline">Subscription Agreement</a>.
                            </div>
                        </label>
                    </div>

                    <!-- Action Button -->
                    <button 
                        type="submit"
                        id="submit-btn"
                        disabled
                        class="w-full py-4 px-6 rounded-xl font-semibold text-lg shadow-none transition-all flex items-center justify-center gap-2 group relative z-10 bg-slate-100 text-slate-400 cursor-not-allowed"
                    >
                        <span id="btn-text">Agree to Terms to Continue</span>
                        <i data-lucide="arrow-right" id="btn-icon" class="w-5 h-5 transition-transform"></i>
                    </button>
                </form>
                <!-- Form End -->

            </div>
        </div>
        
        <!-- Trust Badges -->
        <div class="mt-12 flex flex-wrap justify-center gap-6">
            <div class="flex items-center gap-2.5 px-4 py-2 rounded-full border border-slate-200 bg-white shadow-sm">
                <div class="text-slate-400"><i data-lucide="server" class="w-[18px] h-[18px]"></i></div>
                <span class="text-sm font-medium text-slate-600">Non-Custodial</span>
            </div>
            <div class="flex items-center gap-2.5 px-4 py-2 rounded-full border border-slate-200 bg-white shadow-sm">
                <div class="text-slate-400"><i data-lucide="shield-check" class="w-[18px] h-[18px]"></i></div>
                <span class="text-sm font-medium text-slate-600">Compliance Ready</span>
            </div>
            <div class="flex items-center gap-2.5 px-4 py-2 rounded-full border border-slate-200 bg-white shadow-sm">
                <div class="text-slate-400"><i data-lucide="lock" class="w-[18px] h-[18px]"></i></div>
                <span class="text-sm font-medium text-slate-600">Secure</span>
            </div>
        </div>

    </main>

    <!-- Feature Details -->
    <section class="bg-slate-50 py-16 border-t border-slate-200">
        <div class="max-w-4xl mx-auto px-4">
            <div class="grid md:grid-cols-2 gap-8">
                <!-- Feature Block 1 -->
                <div>
                    <h3 class="font-bold text-slate-900 mb-4 text-lg">Compliance & Security</h3>
                    <ul class="space-y-3">
                        <li class="flex gap-3 text-sm text-slate-600"><span class="text-indigo-400">•</span>ID Verification (KYC) - 200 checks included.</li>
                        <li class="flex gap-3 text-sm text-slate-600"><span class="text-indigo-400">•</span>Secure Private Deal Rooms for due diligence.</li>
                        <li class="flex gap-3 text-sm text-slate-600"><span class="text-indigo-400">•</span>Smart Vault for critical records.</li>
                    </ul>
                </div>
                <!-- Feature Block 2 -->
                <div>
                    <h3 class="font-bold text-slate-900 mb-4 text-lg">Management & Distribution</h3>
                    <ul class="space-y-3">
                        <li class="flex gap-3 text-sm text-slate-600"><span class="text-indigo-400">•</span>Mint and manage compliant digital assets.</li>
                        <li class="flex gap-3 text-sm text-slate-600"><span class="text-indigo-400">•</span>Backer Dashboard for performance tracking.</li>
                        <li class="flex gap-3 text-sm text-slate-600"><span class="text-indigo-400">•</span>Real-time Cap Table updates.</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="bg-white py-16 border-t border-slate-200">
        <div class="max-w-3xl mx-auto px-4">
            <h2 class="text-3xl font-bold text-slate-900 text-center mb-10">Frequently Asked Questions</h2>
            <div class="space-y-6">
                
                <!-- FAQ Item 1 -->
                <div class="border border-slate-200 rounded-lg bg-white overflow-hidden transition-all hover:border-slate-300 shadow-sm">
                    <button type="button" class="faq-toggle w-full text-left px-6 py-4 flex items-center justify-between text-slate-900 font-medium focus:outline-none hover:bg-slate-50">
                        What happens after I proceed to payment?
                        <i data-lucide="help-circle" class="faq-icon w-5 h-5 text-slate-400 transition-transform"></i>
                    </button>
                    <div class="faq-content hidden px-6 pb-4 text-slate-600 text-sm leading-relaxed border-t border-slate-100 pt-4">
                        You will be redirected to our secure payment portal to settle the setup fee. Once processed, our team will begin your environment configuration.
                    </div>
                </div>

                <!-- FAQ Item 2 -->
                <div class="border border-slate-200 rounded-lg bg-white overflow-hidden transition-all hover:border-slate-300 shadow-sm">
                    <button type="button" class="faq-toggle w-full text-left px-6 py-4 flex items-center justify-between text-slate-900 font-medium focus:outline-none hover:bg-slate-50">
                        When is the 3% fee charged?
                        <i data-lucide="help-circle" class="faq-icon w-5 h-5 text-slate-400 transition-transform"></i>
                    </button>
                    <div class="faq-content hidden px-6 pb-4 text-slate-600 text-sm leading-relaxed border-t border-slate-100 pt-4">
                        The platform fee is applied only when you raise capital or distribute assets. There are no fees on dormant projects or unraised funds.
                    </div>
                </div>

                <!-- FAQ Item 3 -->
                <div class="border border-slate-200 rounded-lg bg-white overflow-hidden transition-all hover:border-slate-300 shadow-sm">
                    <button type="button" class="faq-toggle w-full text-left px-6 py-4 flex items-center justify-between text-slate-900 font-medium focus:outline-none hover:bg-slate-50">
                        What does the monthly fee cover?
                        <i data-lucide="help-circle" class="faq-icon w-5 h-5 text-slate-400 transition-transform"></i>
                    </button>
                    <div class="faq-content hidden px-6 pb-4 text-slate-600 text-sm leading-relaxed border-t border-slate-100 pt-4">
                        The monthly fee covers the ongoing maintenance of your non-custodial infrastructure, security monitoring, 200 KYC checks, and hosting of your Backer Dashboard.
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-slate-50 py-12 text-center border-t border-slate-200">
        <!-- REMOVED: Copyright line -->
    </footer>
</div>

<!-- Interactivity Script -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Re-initialize icons just in case
    if(typeof lucide !== 'undefined') lucide.createIcons();

    // Checkbox Logic
    const checkbox = document.getElementById('terms-checkbox');
    const submitBtn = document.getElementById('submit-btn');
    const btnText = document.getElementById('btn-text');
    const btnIcon = document.getElementById('btn-icon');

    checkbox.addEventListener('change', function() {
        if (this.checked) {
            // Enable State
            submitBtn.disabled = false;
            submitBtn.classList.remove('bg-slate-100', 'text-slate-400', 'cursor-not-allowed', 'shadow-none');
            submitBtn.classList.add('bg-indigo-600', 'hover:bg-indigo-700', 'text-white', 'shadow-lg', 'shadow-indigo-600/20', 'cursor-pointer');
            btnText.textContent = "Proceed to Payment";
            btnIcon.classList.add('group-hover:translate-x-1');
        } else {
            // Disabled State
            submitBtn.disabled = true;
            submitBtn.classList.add('bg-slate-100', 'text-slate-400', 'cursor-not-allowed', 'shadow-none');
            submitBtn.classList.remove('bg-indigo-600', 'hover:bg-indigo-700', 'text-white', 'shadow-lg', 'shadow-indigo-600/20', 'cursor-pointer');
            btnText.textContent = "Agree to Terms to Continue";
            btnIcon.classList.remove('group-hover:translate-x-1');
        }
    });

    // FAQ Toggle Logic
    const faqToggles = document.querySelectorAll('.faq-toggle');
    faqToggles.forEach(toggle => {
        toggle.addEventListener('click', () => {
            const content = toggle.nextElementSibling;
            const icon = toggle.querySelector('.faq-icon');
            
            if (content.classList.contains('hidden')) {
                content.classList.remove('hidden');
                icon.classList.add('rotate-180', 'text-indigo-600');
            } else {
                content.classList.add('hidden');
                icon.classList.remove('rotate-180', 'text-indigo-600');
            }
        });
    });
});
</script>