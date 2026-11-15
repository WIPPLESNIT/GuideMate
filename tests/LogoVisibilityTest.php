<?php

echo "=== GuideMate Splash Screen & In-App Logo Verification Test ===\n\n";

$baseDir = dirname(__DIR__);

// 1. Verify Assets
echo "1. Checking Assets...\n";
$assets = [
    'assets/images/splash.png' => 'Launch splash image (PNG)',
    'assets/images/icon.png' => 'App icon (PNG)',
    'public/assets/img/logo-icon.png' => 'Web logo icon (PNG)',
];

$allAssetsPass = true;
foreach ($assets as $relPath => $label) {
    $fullPath = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
    if (!file_exists($fullPath)) {
        echo "  [FAIL] Missing file: $relPath ($label)\n";
        $allAssetsPass = false;
        continue;
    }
    $size = filesize($fullPath);
    $header = file_get_contents($fullPath, false, null, 0, 8);
    $isPng = ($header === "\x89PNG\r\n\x1a\n");
    if (!$isPng || $size < 1000) {
        echo "  [FAIL] Invalid PNG file: $relPath (Size: $size bytes, PNG Header: " . ($isPng ? 'YES' : 'NO') . ")\n";
        $allAssetsPass = false;
    } else {
        echo "  [PASS] $relPath ($label): $size bytes, valid PNG\n";
    }
}

// 2. Verify app.json Splash Configuration
echo "\n2. Checking app.json Splash Configuration...\n";
$appJsonContent = file_get_contents($baseDir . '/app.json');
$appJson = json_decode($appJsonContent, true);

$hasExpoSplash = isset($appJson['expo']['splash']) &&
                 $appJson['expo']['splash']['image'] === './assets/images/splash.png' &&
                 $appJson['expo']['splash']['resizeMode'] === 'contain';

$hasPluginSplash = false;
if (isset($appJson['expo']['plugins']) && is_array($appJson['expo']['plugins'])) {
    foreach ($appJson['expo']['plugins'] as $p) {
        if (is_array($p) && ($p[0] ?? '') === 'expo-splash-screen') {
            $cfg = $p[1] ?? [];
            if (($cfg['image'] ?? '') === './assets/images/splash.png' &&
                ($cfg['resizeMode'] ?? '') === 'contain' &&
                isset($cfg['backgroundColor']) &&
                isset($cfg['dark']['backgroundColor'])) {
                $hasPluginSplash = true;
                break;
            }
        }
    }
}

if ($hasExpoSplash && $hasPluginSplash) {
    echo "  [PASS] app.json has both expo.splash and expo-splash-screen plugin correctly configured\n";
} else {
    echo "  [FAIL] app.json splash configuration missing or incomplete (expo.splash: " . ($hasExpoSplash ? 'YES' : 'NO') . ", plugin: " . ($hasPluginSplash ? 'YES' : 'NO') . ")\n";
    $allAssetsPass = false;
}

// 3. Verify app/_layout.tsx Splash Implementation
echo "\n3. Checking app/_layout.tsx Splash Screen Implementation...\n";
$layoutContent = file_get_contents($baseDir . '/app/_layout.tsx');
$hasPreventAutoHide = (strpos($layoutContent, 'SplashScreen.preventAutoHideAsync') !== false);
$hasHideAsync = (strpos($layoutContent, 'SplashScreen.hideAsync') !== false);
$hasAnimatedSplash = (strpos($layoutContent, 'Animated.View') !== false && strpos($layoutContent, 'splash.png') !== false);
$hasContainResize = (strpos($layoutContent, 'resizeMode="contain"') !== false);
$hasFadeTransition = (strpos($layoutContent, 'Animated.timing') !== false);

if ($hasPreventAutoHide && $hasHideAsync && $hasAnimatedSplash && $hasContainResize && $hasFadeTransition) {
    echo "  [PASS] app/_layout.tsx implements preventAutoHideAsync, animated splash overlay, contain resizeMode, and smooth fade transition\n";
} else {
    echo "  [FAIL] app/_layout.tsx missing splash screen features\n";
    $allAssetsPass = false;
}

// 4. Verify Guidemate logo removed from regular in-app screens
echo "\n4. Checking In-App Regular Screens (Guidemate Logo Removed)...\n";
$screensToCheck = [
    'app/(auth)/login.tsx' => 'Login Screen',
    'app/(auth)/register.tsx' => 'Register Screen',
    'app/(auth)/forgot-password.tsx' => 'Forgot Password Screen',
    'app/(tabs)/index.tsx' => 'Home Screen (Tabs)',
    'app/about.tsx' => 'About Screen',
];

$allScreensClean = true;
foreach ($screensToCheck as $screen => $label) {
    $content = file_get_contents($baseDir . '/' . $screen);
    $hasInAppLogo = (strpos($content, 'accessibilityLabel="GuideMate logo"') !== false) ||
                    (strpos($content, 'headerLogoWrap') !== false) ||
                    (strpos($content, 'brandLogoWrap') !== false);

    if ($hasInAppLogo) {
        echo "  [FAIL] $screen ($label) still displays in-app GuideMate logo element!\n";
        $allScreensClean = false;
    } else {
        echo "  [PASS] $screen ($label) does not display unnecessary GuideMate logo\n";
    }
}

// 5. Verify Web views still have their proper brand mark
echo "\n5. Checking Web Version Logo Preservation...\n";
$webViews = [
    'app/Views/layouts/main.php',
    'app/Views/layouts/admin.php',
    'app/Views/layouts/auth.php',
    'app/Views/auth/login.php',
];

$allWebPass = true;
foreach ($webViews as $view) {
    $content = file_get_contents($baseDir . '/' . $view);
    if (strpos($content, 'img/logo-icon.png') === false) {
        echo "  [FAIL] $view missing web logo\n";
        $allWebPass = false;
    } else {
        echo "  [PASS] $view preserves web logo mark\n";
    }
}

echo "\n=== Verification Summary ===\n";
if ($allAssetsPass && $allScreensClean && $allWebPass) {
    echo "ALL TESTS PASSED SUCCESSFULLY!\n";
    echo "- Logo removed from in-app regular screens.\n";
    echo "- Logo configured as centered launch splash screen with smooth transition in Expo Go & production.\n";
} else {
    echo "SOME TESTS FAILED! Check details above.\n";
}
