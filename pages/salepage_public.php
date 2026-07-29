<?php
/**
 * Public Sale Page Controller
 * * Handles public access to token sale pages via unique URL tokens.
 * Wraps the shared 'salepage.php' content with a public-facing header and SEO tags.
 */

// 1. Session & Database Setup
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Adjust path to db.php based on file location (inside /pages/)
require_once __DIR__ . '/../src/db.php';

// Include security to ensure CSRF token is available for the role switcher
if (file_exists(__DIR__ . '/../src/security.php')) {
    require_once __DIR__ . '/../src/security.php';
}

// 2. Token Resolution
// $GLOBALS['sale_url_token'] is passed from index.php if routed correctly.
// Fallback to URL parsing if accessed directly.
$token = $GLOBALS['sale_url_token'] ?? null;

if (!$token) {
    // Fallback: Try parsing from URI if global is missing
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (preg_match('#/p/([A-Za-z0-9]{6,64})#', $uri, $m)) {
        $token = $m[1];
    }
}

if (!$token) {
    http_response_code(404);
    die("Error: Sale token missing.");
}

// 3. Fetch Project Context & Metadata
try {
    $stmt = $pdo->prepare("
        SELECT 
            tsp.project_id, 
            tsp.sale_name, 
            tsp.status,
            p.project_name,
            p.founder_id,
            tsp.project_description_story,
            tsp.general_images_json
        FROM token_sale_pages tsp
        JOIN projet p ON tsp.project_id = p.id
        WHERE tsp.sale_url = :token
        LIMIT 1
    ");
    $stmt->execute(['token' => $token]);
    $saleData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$saleData) {
        http_response_code(404);
        include __DIR__ . '/404_not_found.php';
        exit;
    }

    // 4. Determine Sale Status
    // We treat 'active' as the only live state. Everything else is 'ended' or 'unavailable'.
    $rawStatus = strtolower($saleData['status'] ?? 'draft');
    $isLive = ($rawStatus === 'live');
    
    // Check if current user is founder to allow preview
    $currentUserId = $_SESSION['user_id'] ?? null;
    $isFounder = ($currentUserId && $currentUserId == $saleData['founder_id']);
    
    // Format status for display (e.g. "ended" -> "Sale Ended")
    $displayStatus = ucfirst($rawStatus); 
    if ($rawStatus === 'draft') $displayStatus = 'Coming Soon';

    // 5. Set Session Context
    $_SESSION['selected_project_id'] = $saleData['project_id'];
    $_SESSION['selected_sale_name'] = $saleData['sale_name'];

    // --- Explicit Login Redirect Handler ---
    // Now runs after session context is set, so the Purchase page knows which project to load.
    if (isset($_GET['trigger_login'])) {
        // UX FIX: Redirect to the ROUTE '/purchase', not the file path '/pages/purchase'
        $_SESSION['redirect_after_login'] = '/purchase';
        header('Location: /login');
        exit;
    }

    // 6. Prepare SEO & Visual Data
    $pageTitle = htmlspecialchars($saleData['project_name']) . " - Private Sale";
    $pageDesc = htmlspecialchars(mb_strimwidth(strip_tags($saleData['project_description_story']), 0, 160, "..."));
    
    $heroImage = '';
    $images = json_decode($saleData['general_images_json'] ?? '[]', true);
    if (!empty($images) && is_array($images) && isset($images[0])) {
        $heroImage = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]/uploads/" . $images[0];
    }

} catch (Exception $e) {
    error_log("Public Sale Error: " . $e->getMessage());
    http_response_code(500);
    die("Internal Server Error");
}
?>
<!doctype html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    
    <title><?php echo $pageTitle; ?></title>
    <meta name="description" content="<?php echo $pageDesc; ?>">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo $pageTitle; ?>">
    <meta property="og:description" content="<?php echo $pageDesc; ?>">
    <?php if($heroImage): ?>
    <meta property="og:image" content="<?php echo $heroImage; ?>">
    <?php endif; ?>

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:title" content="<?php echo $pageTitle; ?>">
    <meta property="twitter:description" content="<?php echo $pageDesc; ?>">
    <?php if($heroImage): ?>
    <meta property="twitter:image" content="<?php echo $heroImage; ?>">
    <?php endif; ?>

    <!-- Fonts & Tailwind -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Config & Icons -->
    <script src="/config_logo.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Montserrat', 'sans-serif'] },
                    colors: {
                        primary: '#000000', 
                        'primary-dark': '#1f2937',
                    }
                }
            }
        }
    </script>
    <style>body { font-family: 'Montserrat', sans-serif; }</style>
</head>
<body class="bg-gray-50 text-gray-900 antialiased selection:bg-gray-200 selection:text-gray-900 flex flex-col min-h-screen">

    <!-- Navigation -->
    <nav class="sticky top-0 z-40 w-full bg-white/80 backdrop-blur-md border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-24 items-center">
                <div class="flex-shrink-0 flex items-center gap-2">
                    <a href="<?= get_url('login') ?>" class="flex items-center gap-2 group">
                        <img id="public-logo" src="" alt="Tookle" class="h-20 w-auto transition-opacity hover:opacity-80">
                    </a>
                    <span class="hidden sm:inline-block px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-500 border border-gray-200 ml-2">
                        Private Sale
                    </span>
                </div>

                <div class="flex items-center gap-3">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="<?= get_url('logout') ?>" class="text-sm font-medium text-gray-500 hover:text-red-500 transition-colors">
                            Log out
                        </a>
                    <?php else: ?>
                        <span class="hidden sm:inline text-sm text-gray-500 mr-2">Already have an account?</span>
                        <a href="?trigger_login=1" class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-black hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900 transition-all shadow-sm">
                            Log in
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-grow relative">
        <?php if ($isLive || $isFounder): ?>
            
            <?php if (!$isLive && $isFounder): ?>
            <!-- Founder Preview Banner -->
            <div class="bg-amber-50 border-b border-amber-200 w-full z-30 relative">
                <div class="max-w-7xl mx-auto px-4 py-3 sm:px-6 lg:px-8 flex items-center justify-center text-center">
                    <i data-lucide="eye-off" class="w-5 h-5 text-amber-600 mr-2"></i>
                    <p class="text-sm font-medium text-amber-800">
                        Page not visible externally - start your private sale to share your private link
                    </p>
                </div>
            </div>
            <?php endif; ?>

            <!-- ACTIVE SALE VIEW -->
            <?php include __DIR__ . '/salepage.php'; ?>
        
        <?php else: ?>
            <!-- NOT LIVE / ENDED VIEW -->
            <div class="relative min-h-[calc(100vh-6rem-4rem)] flex items-center justify-center p-4 overflow-hidden">
                
                <!-- Atmospheric Background (Blurred) -->
                <?php if($heroImage): ?>
                <div class="absolute inset-0 z-0">
                    <img src="<?php echo $heroImage; ?>" class="w-full h-full object-cover opacity-20 blur-md grayscale scale-105" alt="Project Background">
                    <div class="absolute inset-0 bg-gradient-to-t from-gray-50 via-gray-50/60 to-transparent"></div>
                </div>
                <?php endif; ?>

                <!-- Status Card -->
                <div class="relative z-10 w-full max-w-lg bg-white/90 backdrop-blur-xl rounded-3xl shadow-2xl border border-white/50 p-8 md:p-12 text-center animate-fade-in-up">
                    
                    <!-- Icon Badge -->
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-gray-100 mb-6 border-2 border-white shadow-sm">
                        <?php if($rawStatus === 'draft'): ?>
                            <i data-lucide="clock" class="h-8 w-8 text-blue-500"></i>
                        <?php else: ?>
                            <i data-lucide="lock" class="h-8 w-8 text-gray-400"></i>
                        <?php endif; ?>
                    </div>

                    <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-2">
                        <?php echo ($rawStatus === 'draft') ? 'Coming Soon' : 'Sale Concluded'; ?>
                    </h1>
                    
                    <div class="inline-flex items-center px-3 py-1 rounded-full bg-gray-100 text-xs font-semibold text-gray-500 uppercase tracking-wide mb-6">
                        <?php echo htmlspecialchars($saleData['project_name']); ?>
                    </div>

                    <p class="text-gray-500 mb-8 leading-relaxed">
                        <?php if($rawStatus === 'draft'): ?>
                            This private sale project has not started yet. Please check back later.
                        <?php else: ?>
                            This private sale event is is no longer accepting contributions.
                        <?php endif; ?>
                    </p>

                    <div class="flex flex-col sm:flex-row gap-3 justify-center">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="<?= get_url('settings') ?>" class="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-xl text-white bg-black hover:bg-gray-800 transition-all shadow-lg hover:shadow-gray-900/10">
                                Settings
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mt-8 pt-6 border-t border-gray-200/60">
                         <p class="text-xs text-gray-400">Powered by TOOKLE.io</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-auto relative z-20">
        <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
               
                
            </div>
        </div>
    </footer>

    <!-- Pass CSRF Token to JS -->
    <script>
        window.csrfToken = "<?php echo $_SESSION['csrf_token'] ?? ''; ?>";
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
            const logoImg = document.getElementById('public-logo');
            if (logoImg && typeof TOOKLE_LOGO_BASE64 !== 'undefined' && TOOKLE_LOGO_BASE64) {
                logoImg.src = TOOKLE_LOGO_BASE64;
            }

            // --- WIRE UP JOIN BUTTONS ---
            // We attach listeners to hijack the click and force the role switch
            const mainBtn = document.getElementById('top-invest-now-button');
            const stickyBtn = document.getElementById('sticky-invest-button');

            const handleJoinClick = (e) => {
                e.preventDefault(); // Stop the default <a href="<?= get_url('purchase') ?>"> navigation
                joinSaleAsInvestor(e.currentTarget); // Trigger role switch logic
            };

            if (mainBtn) mainBtn.addEventListener('click', handleJoinClick);
            if (stickyBtn) stickyBtn.addEventListener('click', handleJoinClick);
        });

        // --- ROLE SWITCH LOGIC FOR JOINING SALE ---
        async function joinSaleAsInvestor(clickedElement) {
            // Determine which button to update UI for (prefer clicked one, fallback to ID lookup)
            let btn = clickedElement;
            if (!btn || !btn.style) {
                btn = document.getElementById('top-invest-now-button') || document.getElementById('sticky-invest-button');
            }
            
            const originalText = btn ? btn.innerText : 'Join Now';
            
            if (btn) {
                btn.disabled = true; // For <button> elements
                btn.innerText = "Processing...";
                btn.classList.add('opacity-75', 'cursor-not-allowed', 'pointer-events-none'); // Visual disable
                
                // Disable the "other" button too if it exists to prevent double submission
                const otherBtnId = btn.id === 'top-invest-now-button' ? 'sticky-invest-button' : 'top-invest-now-button';
                const otherBtn = document.getElementById(otherBtnId);
                if (otherBtn) {
                     otherBtn.classList.add('opacity-75', 'cursor-not-allowed', 'pointer-events-none');
                }
            }

            if (!window.csrfToken) {
                // If no token, user probably isn't logged in. Redirect to login.
                window.location.href = "?trigger_login=1";
                return;
            }

            try {
                const response = await fetch('/backend/role_switcher.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        role: 'investor', // Force switch to investor
                        csrf_token: window.csrfToken,
                        // UX FIX: Use the ROUTE '/purchase', not the file path
                        redirect_url: '/purchase' 
                    })
                });

                const data = await response.json();

                if (data.success && data.redirect) {
                    window.location.href = data.redirect;
                } else if (data.error === 'Not authenticated') {
                    window.location.href = "?trigger_login=1";
                } else if (data.error === 'membership_required') {
                    // Handle specific membership error
                     if(confirm("Membership required. Would you like to upgrade?")) {
                         window.location.href = data.redirect; // Likely /subscription
                     } else {
                         resetBtn(btn, originalText);
                     }
                } else {
                    alert("Unable to join sale: " + (data.error || "Unknown error"));
                    resetBtn(btn, originalText);
                }
            } catch (err) {
                console.error("Join Sale Error:", err);
                alert("An error occurred. Please try again.");
                resetBtn(btn, originalText);
            }
        }

        function resetBtn(btn, originalText) {
            // Reset the clicked button
            if (btn) {
                btn.disabled = false;
                btn.innerText = originalText;
                btn.classList.remove('opacity-75', 'cursor-not-allowed', 'pointer-events-none');
            }
            
            // Reset the other button too
            const otherIds = ['top-invest-now-button', 'sticky-invest-button'];
            otherIds.forEach(id => {
                const b = document.getElementById(id);
                if (b) b.classList.remove('opacity-75', 'cursor-not-allowed', 'pointer-events-none');
            });
        }
    </script>
</body>
</html>