<?php

// Declare
declare(strict_types=1);

trait DP_brightnessSensors
{
    /**
     * Determines the necessary variables of the brightness sensors.
     */
    public function DetermineBrightnessSensors(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $listedVariables = [];
        $instanceIDs = @IPS_GetInstanceListByModuleID(self::HOMEMATIC_MODULE_GUID);
        if (!empty($instanceIDs)) {
            $variables = [];
            foreach ($instanceIDs as $instanceID) {
                $childrenIDs = @IPS_GetChildrenIDs($instanceID);
                foreach ($childrenIDs as $childrenID) {
                    $match = false;
                    $object = @IPS_GetObject($childrenID);
                    if ($object['ObjectIdent'] == 'ILLUMINATION' || $object['ObjectIdent'] == 'BRIGHTNESS') {
                        $match = true;
                    }
                    if ($match) {
                        // Check for variable
                        if ($object['ObjectType'] == 2) {
                            $name = strstr(@IPS_GetName($instanceID), ':', true);
                            if ($name == false) {
                                $name = @IPS_GetName($instanceID);
                            }
                            array_push($variables, [
                                'UseSensor'                                                 => true,
                                'Name'                                                      => $name,
                                'ID'                                                        => $childrenID]);
                        }
                    }
                }
            }
            // Get already listed variables
            $listedVariables = json_decode($this->ReadPropertyString('BrightnessSensors'), true);
            // Delete non existing variables anymore
            if (!empty($listedVariables)) {
                $deleteVariables = array_diff(array_column($listedVariables, 'ID'), array_column($variables, 'ID'));
                if (!empty($deleteVariables)) {
                    foreach ($deleteVariables as $key => $deleteVariable) {
                        unset($listedVariables[$key]);
                    }
                }
            }
            // Add new variables
            if (!empty($listedVariables)) {
                $addVariables = array_diff(array_column($variables, 'ID'), array_column($listedVariables, 'ID'));
                if (!empty($addVariables)) {
                    foreach ($addVariables as $addVariable) {
                        $name = strstr(@IPS_GetName(@IPS_GetParent($addVariable)), ':', true);
                        array_push($listedVariables, [
                            'UseSensor'                                                 => true,
                            'Name'                                                      => $name,
                            'ID'                                                        => $addVariable]);
                    }
                }
            } else {
                $listedVariables = $variables;
            }
        }
        // Sort variables by name
        usort($listedVariables, function ($a, $b)
        {
            return $a['Name'] <=> $b['Name'];
        });
        // Rebase array
        $listedVariables = array_values($listedVariables);
        // Update variable list
        $json = json_encode($listedVariables);
        @IPS_SetProperty($this->InstanceID, 'BrightnessSensors', $json);
        if (@IPS_HasChanges($this->InstanceID)) {
            @IPS_ApplyChanges($this->InstanceID);
        }
        echo 'Die Helligkeitssensoren wurden automatisch ermittelt!';
    }

    /**
     * Checks the brightness sensors.
     */
    public function CheckBrightnessSensors()
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if (!$this->GetValue('TwilightDetection')) {
            $this->SendDebug(__FUNCTION__, 'Die Dämmerungsprüfung ist ausgeschaltet!', 0);
            return;
        }
        $values = [];
        $brightnessSensors = $this->GetBrightnessSensors();
        if (!empty($brightnessSensors)) {
            foreach ($brightnessSensors as $brightnessSensor) {
                $parent = IPS_GetParent($brightnessSensor);
                // Check battery state is on channel 0
                $config = json_decode(IPS_GetConfiguration($parent));
                $address = strstr($config->Address, ':', true) . ':0';
                $instances = IPS_GetInstanceListByModuleID(self::HOMEMATIC_MODULE_GUID);
                if (!empty($instances)) {
                    foreach ($instances as $instance) {
                        $config = json_decode(IPS_GetConfiguration($instance));
                        if ($config->Address == $address) {
                            $children = IPS_GetChildrenIDs($instance);
                            if (!empty($children)) {
                                foreach ($children as $child) {
                                    $ident = IPS_GetObject($child)['ObjectIdent'];
                                    if ($ident == 'LOWBAT' || $ident == 'LOW_BAT') {
                                        $lowBattery = (boolean) GetValue($child);
                                        if (!$lowBattery) {
                                            $value = (float) GetValue($brightnessSensor);
                                            $this->SendDebug(__FUNCTION__, 'Die Helligkeit von ' . $brightnessSensor . ' beträgt: ' . $value, 0);
                                            array_push($values, $value);
                                        } else {
                                            $this->SendDebug(__FUNCTION__, 'Die Batterie von ' . $brightnessSensor . ' ist schwach. Helligkeit wird nicht ausgewertet!', 0);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        if (!empty($values)) {
            $averageBrightness = round(array_sum($values) / count($values), 1);
            $this->SendDebug(__FUNCTION__, 'Der Mittelwert ist: ' . $averageBrightness, 0);
            $timestamp = date('d.m.Y, H:i:s');
            $this->SetValue('LastUpdate', $timestamp);
            $this->SetValue('AverageValue', $averageBrightness);
            // Check day
            $thresholdDay = $this->ReadPropertyFloat('ThresholdDay');
            if ($averageBrightness >= $thresholdDay) {
                $this->SendDebug(__FUNCTION__, 'Es ist Tag.', 0);
                $this->SetValue('DayNightDetection', false);
            }
            // Check night
            $thresholdNight = $this->ReadPropertyFloat('ThresholdNight');
            if ($averageBrightness <= $thresholdNight) {
                $this->SendDebug(__FUNCTION__, 'Es ist Nacht.', 0);
                $this->SetValue('DayNightDetection', true);
            }
        }
    }

    //#################### Private

    /**
     * Gets the used brightness sensors.
     *
     * @return array
     * Returns an array of used brightness sensors.
     */
    private function GetBrightnessSensors(): array
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $sensors = [];
        $brightnessSensors = json_decode($this->ReadPropertyString('BrightnessSensors'));
        if (!empty($brightnessSensors)) {
            foreach ($brightnessSensors as $brightnessSensor) {
                $id = $brightnessSensor->ID;
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    $useSensor = $brightnessSensor->UseSensor;
                    if ($useSensor) {
                        array_push($sensors, $id);
                    }
                }
            }
        }
        return $sensors;
    }
}
