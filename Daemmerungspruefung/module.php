<?php

/*
 * @module      Daemmerungspruefung
 *
 * @prefix      DP
 *
 * @file        module.php
 *
 * @developer   Ulrich Bittner
 * @copyright   (c) 2020
 * @license     CC BY-NC-SA 4.0
 *              https://creativecommons.org/licenses/by-nc-sa/4.0/
 *
 * @version     1.00-1
 * @date        2020-02-23, 18:00, 1582477200
 * @review      2020-02-23, 18:00
 *
 * @see         https://github.com/ubittner/Daemmerungspruefung
 *
 * @guids       Library
 *              {7D9ECD9E-7C2C-1417-332E-D07DA7EE89D1}
 *
 *              Daemmerungspruefung
 *             	{37801978-C8C9-E58E-9011-CFF32B74D9D9}
 */

// Declare
declare(strict_types=1);

// Include
include_once __DIR__ . '/helper/autoload.php';

class Daemmerungspruefung extends IPSModule
{
    // Helper
    use DP_brightnessSensors;

    // Constants
    private const HOMEMATIC_MODULE_GUID = '{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}';

    public function Create()
    {
        // Never delete this line!
        parent::Create();

        // Register properties
        $this->RegisterProperties();

        // Create profiles
        $this->CreateProfiles();

        // Register variables
        $this->RegisterVariables();
    }

    public function ApplyChanges()
    {
        // Wait until IP-Symcon is started
        $this->RegisterMessage(0, IPS_KERNELSTARTED);

        // Never delete this line!
        parent::ApplyChanges();

        // Check runlevel
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }

        // Validate configuration
        $this->ValidateConfiguration();

        // Set options
        $this->SetOptions();

        // Register messages
        $this->RegisterMessages();

        // Check brightness sensors
        $this->CheckBrightnessSensors();
    }

    public function Destroy()
    {
        // Never delete this line!
        parent::Destroy();

        // Delete profiles
        $this->DeleteProfiles();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->SendDebug(__FUNCTION__, 'SenderID: ' . $SenderID . ', Message: ' . $Message . ', Data: ' . print_r($Data, true), 0);
        if (!empty($Data)) {
            foreach ($Data as $key => $value) {
                $this->SendDebug(__FUNCTION__, 'Data[' . $key . '] = ' . json_encode($value), 0);
            }
        }
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;

            // $Data[0] = actual value
            // $Data[1] = difference to last value
            // $Data[2] = last value
            case VM_UPDATE:
                // Brightness sensors
                $brightnessSensors = json_decode($this->ReadPropertyString('BrightnessSensors'), true);
                if (!empty($brightnessSensors)) {
                    if (array_search($SenderID, array_column($brightnessSensors, 'ID')) !== false) {
                        if ($Data[1]) {
                            $this->CheckBrightnessSensors();
                        }
                    }
                }
                break;

        }
    }

    public function GetConfigurationForm()
    {
        $formdata = json_decode(file_get_contents(__DIR__ . '/form.json'));
        // Registered messages
        $registeredVariables = $this->GetMessageList();
        foreach ($registeredVariables as $senderID => $messageID) {
            $senderName = IPS_GetName($senderID);
            $parentName = $senderName;
            $parentID = IPS_GetParent($senderID);
            if (is_int($parentID) && $parentID != 0 && @IPS_ObjectExists($parentID)) {
                $parentName = IPS_GetName($parentID);
            }
            switch ($messageID) {
                case [10001]:
                    $messageDescription = 'IPS_KERNELSTARTED';
                    break;

                case [10603]:
                    $messageDescription = 'VM_UPDATE';
                    break;

                case [10803]:
                    $messageDescription = 'EM_UPDATE';
                    break;

                default:
                    $messageDescription = 'keine Bezeichnung';
            }
            $formdata->elements[4]->items[0]->values[] = [
                'ParentName'         => $parentName,
                'SenderID'           => $senderID,
                'SenderName'         => $senderName,
                'MessageID'          => $messageID,
                'MessageDescription' => $messageDescription];
        }
        return json_encode($formdata);
    }

    public function ReloadConfiguration()
    {
        $this->ReloadForm();
    }

    //#################### Request action

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'TwilightDetection':
                $this->SetValue($Ident, $Value);
                if ($Value) {
                    $this->CheckBrightnessSensors();
                }
                break;

        }
    }

    protected function KernelReady(): void
    {
        $this->ApplyChanges();
    }

    //##################### Private

    private function RegisterProperties(): void
    {
        // Visibility
        $this->RegisterPropertyBoolean('EnableTwilightDetection', true);
        $this->RegisterPropertyBoolean('EnableDayNightDetection', true);
        $this->RegisterPropertyBoolean('EnableLastUpdate', true);
        $this->RegisterPropertyBoolean('EnableAverageValue', true);
        $this->RegisterPropertyBoolean('EnableThresholdDay', true);
        $this->RegisterPropertyBoolean('EnableThresholdNight', true);

        // Brightness sensors
        $this->RegisterPropertyString('BrightnessSensors', '[]');

        // Thresholds
        $this->RegisterPropertyFloat('ThresholdDay', 60.5);
        $this->RegisterPropertyFloat('ThresholdNight', 20.5);
    }

    private function CreateProfiles(): void
    {
        // Day and night detection
        $profile = 'DP.' . $this->InstanceID . '.DayNightDetection';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileAssociation($profile, 0, 'Es ist Tag', 'Sun', 0xFFFF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Es ist Nacht', 'Moon', 0x0000FF);
    }

    private function DeleteProfiles(): void
    {
        $profiles = ['DayNightDetection'];
        foreach ($profiles as $profile) {
            $profileName = 'DP.' . $this->InstanceID . '.' . $profile;
            if (@IPS_VariableProfileExists($profileName)) {
                IPS_DeleteVariableProfile($profileName);
            }
        }
    }

    private function RegisterVariables(): void
    {
        // Twilight detection
        $this->RegisterVariableBoolean('TwilightDetection', 'Dämmerungsprüfung', '~Switch', 0);
        $this->EnableAction('TwilightDetection');

        // Day and night detection
        $profile = 'DP.' . $this->InstanceID . '.DayNightDetection';
        $this->RegisterVariableBoolean('DayNightDetection', 'Tag- / Nachterkennung', $profile, 1);

        // Last update
        $this->RegisterVariableString('LastUpdate', 'Letzte Aktualisierung', '', 2);
        IPS_SetIcon($this->GetIDForIdent('LastUpdate'), 'Clock');

        // Average value
        $this->RegisterVariableString('AverageValue', 'Mittelwert', '', 3);
        IPS_SetIcon($this->GetIDForIdent('AverageValue'), 'Graph');

        // Threshold day
        $this->RegisterVariableString('ThresholdDay', "Schwellenwert 'Es ist Tag'", '', 4);
        IPS_SetIcon($this->GetIDForIdent('ThresholdDay'), 'Sun');

        // Threshold night
        $this->RegisterVariableString('ThresholdNight', "Schwellenwert 'Es ist Nacht'", '', 5);
        IPS_SetIcon($this->GetIDForIdent('ThresholdNight'), 'Moon');
    }

    private function SetOptions(): void
    {
        // Twilight detection
        IPS_SetHidden($this->GetIDForIdent('TwilightDetection'), !$this->ReadPropertyBoolean('EnableTwilightDetection'));
        $this->EnableAction('TwilightDetection');

        // Day and night detection
        IPS_SetHidden($this->GetIDForIdent('DayNightDetection'), !$this->ReadPropertyBoolean('EnableDayNightDetection'));

        // Last update
        IPS_SetHidden($this->GetIDForIdent('LastUpdate'), !$this->ReadPropertyBoolean('EnableLastUpdate'));

        // Average value
        IPS_SetHidden($this->GetIDForIdent('AverageValue'), !$this->ReadPropertyBoolean('EnableAverageValue'));

        // Threshold day
        $this->SetValue('ThresholdDay', $this->ReadPropertyFloat('ThresholdDay'));
        IPS_SetHidden($this->GetIDForIdent('ThresholdDay'), !$this->ReadPropertyBoolean('EnableThresholdDay'));

        // Threshold night
        $this->SetValue('ThresholdNight', $this->ReadPropertyFloat('ThresholdNight'));
        IPS_SetHidden($this->GetIDForIdent('ThresholdNight'), !$this->ReadPropertyBoolean('EnableThresholdNight'));
    }

    private function UnregisterMessages(): void
    {
        foreach ($this->GetMessageList() as $id => $registeredMessage) {
            foreach ($registeredMessage as $messageType) {
                if ($messageType == VM_UPDATE) {
                    $this->UnregisterMessage($id, VM_UPDATE);
                }
            }
        }
    }

    private function RegisterMessages(): void
    {
        // Unregister first
        $this->UnregisterMessages();
        // Brightness sensors
        $brightnessSensors = $this->GetBrightnessSensors();
        if (!empty($brightnessSensors)) {
            foreach ($brightnessSensors as $id) {
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    $this->RegisterMessage($id, VM_UPDATE);
                }
            }
        }
    }

    private function ValidateConfiguration(): void
    {
        $state = 102;
        // Brightness sensors
        $brightnessSensors = json_decode($this->ReadPropertyString('BrightnessSensors'));
        if (!empty($brightnessSensors)) {
            foreach ($brightnessSensors as $brightnessSensor) {
                $id = $brightnessSensor->ID;
                // Check object
                if ($id != 0) {
                    if (!@IPS_ObjectExists($id)) {
                        $this->LogMessage('Konfiguration Helligkeitssensoren: ID ungültig!', KL_ERROR);
                        $state = 200;
                    } else {
                        // Check ident
                        $object = @IPS_GetObject($id);
                        $ident = false;
                        if ($object['ObjectIdent'] == 'ILLUMINATION' || $object['ObjectIdent'] == 'BRIGHTNESS') {
                            $ident = true;
                        }
                        if (!$ident) {
                            $this->LogMessage('Konfiguration Helligkeitssensoren: Ident ungültig!', KL_ERROR);
                            $state = 200;
                        }
                        // Check instance
                        $instance = IPS_GetInstance(IPS_GetParent($id));
                        $moduleID = $instance['ModuleInfo']['ModuleID'];
                        $this->SendDebug(__FUNCTION__, 'ModuleID: ' . json_encode($moduleID), 0);
                        if ($moduleID !== self::HOMEMATIC_MODULE_GUID) {
                            $this->LogMessage('Konfiguration Helligkeitssensoren: Instanz, GUID ungültig!', KL_ERROR);
                            $state = 200;
                        } else {
                            // Check channel
                            $config = json_decode(IPS_GetConfiguration(IPS_GetParent($id)));
                            $address = strstr($config->Address, ':', false);
                            if ($address != ':1') {
                                $this->LogMessage('Konfiguration Helligkeitssensoren: Instanz, Kanal ungültig!', KL_ERROR);
                                $state = 200;
                            }
                        }
                    }
                }
            }
        }
        // Set state
        $this->SetStatus($state);
    }
}