<?php

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Router;

echo "====================================================================\n";
echo " Router Closure Support and web.php Route Loading Tests\n";
echo "====================================================================\n\n";

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $message): void {
    global $passed, $failed;
    if ($condition) {
        echo " [\033[32mPASS\033[0m] $message\n";
        $passed++;
    } else {
        echo " [\033[31mFAIL\033[0m] $message\n";
        $failed++;
    }
}

// -----------------------------------------------------------------------------
// Test 1: Load routes/web.php with Router instance
// -----------------------------------------------------------------------------
echo "--- Test 1: Load routes/web.php ---\n";

try {
    $router = new Router();
    require __DIR__ . '/../routes/web.php';
    assertTest(true, "routes/web.php loaded without TypeError or any exception");
} catch (\Throwable $e) {
    assertTest(false, "routes/web.php threw exception: " . $e->getMessage());
}

// -----------------------------------------------------------------------------
// Test 2: Closure Route Registration & Execution
// -----------------------------------------------------------------------------
echo "\n--- Test 2: Router Closure Registration and Execution ---\n";

try {
    $r = new Router();
    $closureExecuted = false;
    $closureParam = null;

    $r->get('/test-closure/{name}', function(string $name) use (&$closureExecuted, &$closureParam) {
        $closureExecuted = true;
        $closureParam = $name;
    });

    $r->dispatch('GET', '/test-closure/cebu');

    assertTest($closureExecuted, "Closure route handler executed upon dispatch");
    assertTest($closureParam === 'cebu', "Closure received named parameter ('cebu')");
} catch (\Throwable $e) {
    assertTest(false, "Closure dispatch threw exception: " . $e->getMessage());
}

// -----------------------------------------------------------------------------
// Test 3: Standard Controller Array Route Registration & Execution
// -----------------------------------------------------------------------------
echo "\n--- Test 3: Standard Controller Array Route Handler ---\n";

try {
    $r2 = new Router();
    $r2->get('/areas/{slug}', [\App\Controllers\HomeController::class, 'area']);
    assertTest(true, "Standard [Controller::class, 'method'] handler registered successfully");
} catch (\Throwable $e) {
    assertTest(false, "Controller handler registration failed: " . $e->getMessage());
}

// -----------------------------------------------------------------------------
// Test 4: helper redirect() with status code support
// -----------------------------------------------------------------------------
echo "\n--- Test 4: redirect() Helper Signature & Status Code ---\n";

$refl = new \ReflectionFunction('redirect');
$params = $refl->getParameters();

assertTest(count($params) >= 2, "redirect() accepts at least 2 parameters (path, status)");
assertTest($params[0]->getName() === 'path', "redirect() first parameter is 'path'");
assertTest($params[1]->getName() === 'status' && $params[1]->isDefaultValueAvailable(), "redirect() second parameter is optional 'status'");
assertTest($params[1]->getDefaultValue() === 302, "redirect() default status is 302");

echo "\n--------------------------------------------------------------------\n";
echo "Results: $passed passed, $failed failed\n";
echo "--------------------------------------------------------------------\n";

exit($failed === 0 ? 0 : 1);
