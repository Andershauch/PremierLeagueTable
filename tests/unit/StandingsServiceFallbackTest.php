<?php

class MiniTestFailingProvider implements PLT_Standings_Provider
{
    public function get_provider_key(): string
    {
        return 'failing';
    }

    public function get_standings(string $competition_key, array $context = [])
    {
        return new WP_Error('boom', 'Primary WSL provider is down.');
    }
}

class MiniTestWorkingProvider implements PLT_Standings_Provider
{
    private string $key;

    public function __construct(string $key = 'working')
    {
        $this->key = $key;
    }

    public function get_provider_key(): string
    {
        return $this->key;
    }

    public function get_standings(string $competition_key, array $context = [])
    {
        return ['provider' => $this->key, 'rows' => [['team_name' => 'Test FC']]];
    }
}

class MiniTestCallCountingProvider implements PLT_Standings_Provider
{
    public int $callCount = 0;

    public function get_provider_key(): string
    {
        return 'counting';
    }

    public function get_standings(string $competition_key, array $context = [])
    {
        $this->callCount++;

        return new WP_Error('boom', 'Always fails.');
    }
}

MiniTest::suite('PLT_Standings_Service WSL provider fallback', function (): void {
    $clubMap = new PLT_Club_Map();

    $service = new PLT_Standings_Service(
        new MiniTestWorkingProvider('pl-unused-in-this-test'),
        [new MiniTestFailingProvider(), new MiniTestWorkingProvider('fallback')],
        $clubMap
    );
    $result = $service->get_wsl_standings(1800);
    MiniTest::assertTrue(! is_wp_error($result), 'falls through to the working fallback provider');
    MiniTest::assertSame('fallback', $result['provider'], 'the result comes from the fallback provider');

    $bothFail = new PLT_Standings_Service(
        new MiniTestWorkingProvider(),
        [new MiniTestFailingProvider(), new MiniTestFailingProvider()],
        $clubMap
    );
    $errorResult = $bothFail->get_wsl_standings(1800);
    MiniTest::assertTrue(is_wp_error($errorResult), 'returns a clean WP_Error, not a fatal, when every provider fails');

    $primaryCounter = new MiniTestCallCountingProvider();
    $fallbackNeverCalled = new MiniTestCallCountingProvider();
    $primaryOnly = new PLT_Standings_Service(
        new MiniTestWorkingProvider(),
        [new MiniTestWorkingProvider('primary-succeeds'), $fallbackNeverCalled],
        $clubMap
    );
    $successResult = $primaryOnly->get_wsl_standings(1800);
    MiniTest::assertSame('primary-succeeds', $successResult['provider'], 'uses the primary result when it succeeds');
    MiniTest::assertSame(0, $fallbackNeverCalled->callCount, 'the fallback provider is never called when the primary succeeds');

    $noProviders = new PLT_Standings_Service(new MiniTestWorkingProvider(), [], $clubMap);
    MiniTest::assertTrue(is_wp_error($noProviders->get_wsl_standings(1800)), 'an empty provider list produces a clean error, not a crash');
});
