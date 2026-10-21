<?php

namespace AlwaysOpen\ProcessStamps\Tests;

use AlwaysOpen\ProcessStamps\ProcessStamp;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

class ProcessStampCacheTest extends TestCase
{
    use DatabaseTransactions;

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('process-stamps.cache.enabled', true);
    }

    /**
     * A stamp that is not the first row, so an id read back as `true` or `1` cannot pass.
     */
    private function currentStampAfterAnother(): void
    {
        ProcessStamp::firstOrCreateByProcess(['type' => 'artisan', 'name' => 'some:other-command']);
    }

    #[Test]
    public function the_current_process_id_is_read_from_the_table_once()
    {
        $this->currentStampAfterAnother();

        $first = ProcessStamp::getCurrentProcessId();

        DB::enableQueryLog();
        $second = ProcessStamp::getCurrentProcessId();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $stamp = ProcessStamp::query()->where('hash', ProcessStamp::makeProcessHash(ProcessStamp::getProcessName()))->sole();

        $this->assertSame($stamp->getKey(), $first);
        $this->assertSame($first, $second);
        $this->assertSame(0, $queries);
    }

    #[Test]
    public function the_current_process_id_is_read_from_the_table_on_every_call_without_the_cache()
    {
        config(['process-stamps.cache.enabled' => false]);
        ProcessStamp::getCurrentProcessId();

        DB::enableQueryLog();
        ProcessStamp::getCurrentProcessId();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertGreaterThan(0, $queries);
    }

    #[Test]
    public function an_id_created_in_a_transaction_that_rolls_back_is_not_cached()
    {
        $this->currentStampAfterAnother();

        $key = 'process-stamps:' . ProcessStamp::makeProcessHash(ProcessStamp::getProcessName());

        DB::beginTransaction();
        ProcessStamp::getCurrentProcessId();
        $cachedInsideTransaction = Cache::has($key);
        DB::rollBack();

        $this->assertFalse($cachedInsideTransaction);
        $this->assertFalse(Cache::has($key));

        $current = ProcessStamp::getCurrentProcessId();

        $this->assertTrue(ProcessStamp::query()->whereKey($current)->exists());
        $this->assertSame($current, Cache::get($key));
    }

    #[Test]
    public function an_id_created_in_a_transaction_is_cached_once_it_commits()
    {
        $key = 'process-stamps:' . ProcessStamp::makeProcessHash(ProcessStamp::getProcessName());

        DB::transaction(fn () => ProcessStamp::getCurrentProcessId());

        $this->assertTrue(ProcessStamp::query()->whereKey(Cache::get($key))->exists());
    }
}
