<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Common\FileSystem;

use Internal\DLoad\Module\Common\FileSystem\FS;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataSet;
use Testo\Test;

#[Test]
#[Covers(FS::class)]
final class FSTest
{
    #[DataSet(['rr'], 'a plain name')]
    #[DataSet(['rr.exe'], 'a name with an extension')]
    #[DataSet(['.hidden'], 'a dot file')]
    #[DataSet(['..rr'], 'leading dots')]
    #[DataSet(['c++'], 'regex metacharacters')]
    #[DataSet(['my tool'], 'an inner space')]
    #[DataSet(['console'], 'a reserved device name as a prefix')]
    #[DataSet(['com10'], 'a device number out of the reserved range')]
    #[DataSet(['com0'], 'device number zero')]
    #[DataSet(['xnul'], 'a reserved device name as a suffix')]
    public function acceptsAFileName(string $name): void
    {
        Assert::true(FS::isFileName($name));
    }

    #[DataSet([''], 'empty')]
    #[DataSet(['  '], 'blank')]
    #[DataSet(['.'], 'the current directory')]
    #[DataSet(['..'], 'the parent directory')]
    #[DataSet(['../.git/hooks/pre-commit'], 'a parent traversal')]
    #[DataSet(['bin/rr'], 'a slash')]
    #[DataSet(['bin\\rr'], 'a backslash')]
    #[DataSet(['/usr/bin/rr'], 'an absolute POSIX path')]
    #[DataSet(['C:rr'], 'a drive-relative Windows path')]
    #[DataSet(['rr:stream'], 'an NTFS stream')]
    #[DataSet(["rr\0.exe"], 'a null byte')]
    #[DataSet(['rr.'], 'a trailing dot')]
    #[DataSet(['rr '], 'a trailing space')]
    #[DataSet(['rr. .'], 'trailing dots and spaces')]
    #[DataSet(['nul'], 'a reserved device name')]
    #[DataSet(['CON.txt'], 'a reserved device name with an extension')]
    #[DataSet(['com1'], 'a numbered reserved device name')]
    #[DataSet(['Lpt9.tar.gz'], 'a reserved device name with a double extension')]
    #[DataSet(['con.d'], 'a reserved device name with a short extension')]
    #[DataSet(['CONIN$'], 'the console input device')]
    #[DataSet(['conout$.txt'], 'the console output device with an extension')]
    #[DataSet(['COM¹'], 'a superscript port number')]
    #[DataSet(['lpt³.log'], 'a superscript printer number with an extension')]
    #[DataSet(['CONIN$'], 'the console input device')]
    #[DataSet(['conout$.txt'], 'the console output device with an extension')]
    #[DataSet(['COM¹'], 'a port with a superscript digit')]
    #[DataSet(['lpt³'], 'a printer with a superscript digit')]
    public function rejectsWhatIsNotAFileName(string $name): void
    {
        Assert::false(FS::isFileName($name));
    }

    #[DataSet(['rr-linux-amd64.tar.gz', 'rr-linux-amd64.tar.gz'], 'a file name')]
    #[DataSet(['Binary: tool.tar.gz', 'Binary_ tool.tar.gz'], 'a colon')]
    #[DataSet(['../aux.zip', '.._aux.zip'], 'a parent traversal')]
    #[DataSet(['a/b\\c', 'a_b_c'], 'slashes')]
    #[DataSet(["a\x00b\x1Bc\x7Fd", 'a_b_c_d'], 'control characters')]
    #[DataSet(['tool.zip. .', 'tool.zip'], 'trailing dots and spaces')]
    #[DataSet(['aux.tar.gz', '_aux.tar.gz'], 'a reserved device name')]
    #[DataSet(['con', '_con'], 'a bare reserved device name')]
    #[DataSet(['NUL.', '_NUL'], 'a reserved device name with a trailing dot')]
    #[DataSet(['..', '_'], 'the parent directory')]
    #[DataSet(['.', '_'], 'the current directory')]
    #[DataSet([' ', '_'], 'a space')]
    #[DataSet(['', '_'], 'empty')]
    public function turnsTextIntoAFileName(string $text, string $name): void
    {
        $result = FS::toFileName($text);

        Assert::same($result, $name);
        Assert::true(FS::isFileName($result));
    }
}
