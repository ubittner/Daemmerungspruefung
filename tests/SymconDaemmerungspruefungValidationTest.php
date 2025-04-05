<?php

declare(strict_types=1);

namespace tests;

use TestCaseSymconValidation;

include_once __DIR__ . '/stubs/Validator.php';

class SymconDaemmerungspruefungValidationTest extends TestCaseSymconValidation
{
    public function testValidateDaemmerungspruefung(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }

    public function testValidateModule_Daemmerungspruefung(): void
    {
        $this->validateModule(__DIR__ . '/../Daemmerungspruefung');
    }
}