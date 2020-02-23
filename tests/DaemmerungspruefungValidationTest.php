<?php

declare(strict_types=1);
include_once __DIR__ . '/stubs/Validator.php';
class DaemmerungspruefungValidationTest extends TestCaseSymconValidation
{
    public function testValidateDaemmerungspruefung(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }
    public function testValidateDaemmerungspruefungModule(): void
    {
        $this->validateModule(__DIR__ . '/../Daemmerungspruefung');
    }
}