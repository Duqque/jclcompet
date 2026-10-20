<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Core\ValidationException;
use App\Core\Validator;
use App\Services\CsvService;
use App\Services\Markdown;
use App\Services\Migrator;
use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    public function testMarkdownEscapesHtmlAndUnsafeLinks(): void
    {
        $html = Markdown::render("<script>alert(1)</script> **gras**\n\n- [ok](https://exemple.fr)\n- [ko](javascript:alert(1))");
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('<strong>gras</strong>', $html);
        $this->assertStringContainsString('href="https://exemple.fr"', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function testCsvRoundTripAndFormulaInjectionProtection(): void
    {
        $csv = CsvService::build(['nom', 'prenom'], [['=CMD()', 'Léa'], ['DUPONT', 'Zoé']]);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $rows = CsvService::parse($csv);
        $this->assertSame("'=CMD()", $rows[0]['nom']);
        $this->assertSame('Zoé', $rows[1]['prenom']);
        $rows = CsvService::parse("Nom,Prénom,Date de naissance\nMARTIN,Paul,01/02/2015");
        $this->assertSame('01/02/2015', $rows[0]['date_naissance']);
    }

    public function testValidatorRejectsInvalidData(): void
    {
        $this->expectException(ValidationException::class);
        Validator::validate(['email' => 'pas-un-mail', 'n' => 'abc'], ['email' => 'required|email', 'n' => 'int', 'x' => 'required']);
    }

    public function testValidatorCleansValues(): void
    {
        $d = Validator::validate(['n' => '12', 'w' => '28,5', 'dt' => '2026-09-26T08:30', 'b' => 'on'], ['n' => 'int', 'w' => 'numeric', 'dt' => 'datetime', 'b' => 'bool', 'z' => 'nullable']);
        $this->assertSame(12, $d['n']);
        $this->assertSame(28.5, $d['w']);
        $this->assertSame('2026-09-26 08:30:00', $d['dt']);
        $this->assertSame(1, $d['b']);
        $this->assertNull($d['z']);
    }

    public function testSqlSplitterHandlesQuotesAndComments(): void
    {
        $st = Migrator::splitStatements("-- commentaire ; ignoré\nINSERT INTO t VALUES ('a;b', 'l''apostrophe');\nSELECT 1;");
        $this->assertCount(2, $st);
        $this->assertStringContainsString("'a;b'", $st[0]);
    }
}
