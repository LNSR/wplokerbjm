<?php

declare(strict_types=1);

// 1. Define a dummy attribute accepting a static closure
#[Attribute(Attribute::TARGET_METHOD)]
class TestAction {
    public function __construct(
        public string $hook,
        public \Closure|null $condition = null,
    ) {}
}

// 2. Define a test class using your exact static closure condition
class SampleService {
    #[TestAction(
        hook: 'init',
        condition: static function (SampleService $s): bool {
            return true;
        }
    )]
    public function handleInit(): void {}
}

// 3. Reflect the attribute to extract the static closure
$ref = new ReflectionMethod(SampleService::class, 'handleInit');
$attributes = $ref->getAttributes(TestAction::class);
$actionInstance = $attributes[0]->newInstance();

$registrationPayload = [
    'class' => SampleService::class,
    'method' => 'handleInit',
    'hook' => $actionInstance->hook,
    'condition' => $actionInstance->condition,
];

echo "--- 1. Testing var_export() ---" . PHP_EOL;

try {
    $exported = var_export($registrationPayload, true);
    echo "SUCCESS Output:" . PHP_EOL;
    echo $exported . PHP_EOL;
} catch (\Throwable $e) {
    echo "FAILED with Exception:" . PHP_EOL;
    echo $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "--- 2. Testing \Closure::__set_state() behavior (If var_export succeeded) ---" . PHP_EOL;

if (isset($exported)) {
    try {
        $evaluated = eval("return " . $exported . ";");
        echo "eval() SUCCESS! Type of 'condition' in evaluated array: ";
        var_dump($evaluated['condition']);
    } catch (\Throwable $e) {
        echo "eval() FAILED with Exception:" . PHP_EOL;
        echo $e->getMessage() . PHP_EOL;
    }
}