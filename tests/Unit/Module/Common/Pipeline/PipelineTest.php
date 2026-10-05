<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Common\Pipeline;

use Internal\DLoad\Module\Common\Pipeline\Pipeline;
use Internal\DLoad\Tests\Unit\Module\Common\Pipeline\Stub\HaltInterceptor;
use Internal\DLoad\Tests\Unit\Module\Common\Pipeline\Stub\TraceInterceptor;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Covers(Pipeline::class)]
final class PipelineTest
{
    #[Test]
    public function interceptorsWrapTheLastHandlerInTheGivenOrder(): void
    {
        $trace = self::run(new TraceInterceptor('a'), new TraceInterceptor('b'));

        Assert::same($trace, ['a:in', 'b:in', 'last', 'b:out', 'a:out']);
    }

    #[Test]
    public function withoutInterceptorsOnlyTheLastHandlerRuns(): void
    {
        Assert::same(self::run(), ['last']);
    }

    #[Test]
    public function anInterceptorCanEndTheChainWithoutCallingNext(): void
    {
        $trace = self::run(new TraceInterceptor('a'), new HaltInterceptor(), new TraceInterceptor('b'));

        Assert::same($trace, ['a:in', 'halt', 'a:out']);
    }

    #[Test]
    public function aPipelineCanRunAgain(): void
    {
        $pipeline = Pipeline::prepare(new TraceInterceptor('a'))->with(self::last(...), 'handle');

        $first = $pipeline(new \ArrayObject());
        $second = $pipeline(new \ArrayObject());

        Assert::same($first->getArrayCopy(), $second->getArrayCopy());
    }

    /**
     * @return list<string>
     */
    private static function run(TraceInterceptor|HaltInterceptor ...$interceptors): array
    {
        $trace = new \ArrayObject();
        Pipeline::prepare(...$interceptors)->with(self::last(...), 'handle')($trace);

        return $trace->getArrayCopy();
    }

    /**
     * @param \ArrayObject<int, string> $trace
     * @return \ArrayObject<int, string>
     */
    private static function last(\ArrayObject $trace): \ArrayObject
    {
        $trace[] = 'last';

        return $trace;
    }
}
