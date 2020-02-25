<?php

// Declare
declare(strict_types=1);

trait DP_scriptControl
{
    /**
     * Executes a script on status change.
     *
     * @return bool
     */
    public function ExecuteScript(): bool
    {
        $result = true;
        if (!$this->GetValue('TwilightDetection')) {
            return false;
        }
        if (!$this->ReadPropertyBoolean('ExecuteScript')) {
            return false;
        }
        $script = $this->ReadPropertyInteger('Script');
        if ($script != 0 && @IPS_ObjectExists($script)) {
            $twilightState = $this->GetValue('TwilightState');
            $this->SendDebug(__FUNCTION__, 'Dämmerungsstatus: ' . json_encode($twilightState), 0);
            $result = IPS_RunScriptEx($script, ['TwilightState' => $twilightState]);
        }
        return $result;
    }
}