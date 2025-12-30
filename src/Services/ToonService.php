<?php

declare(strict_types=1);

namespace Aotr\DynamicLevelHelper\Services;

use MischaSigtermans\ToonLaravel\ToonApi;
use Illuminate\Support\Collection;

/**
 * Toon Service Wrapper for managing Toon energy/thermostat data
 * 
 * This service provides a wrapper around the Laravel Toon API package
 * for easy integration with Toon smart home devices.
 */
class ToonService
{
    /**
     * @var ToonApi
     */
    protected ToonApi $toonApi;

    /**
     * Create a new ToonService instance.
     *
     * @param ToonApi|null $toonApi
     */
    public function __construct(ToonApi $toonApi = null)
    {
        $this->toonApi = $toonApi ?? new ToonApi();
    }

    /**
     * Get current temperature and thermostat information.
     *
     * @return array
     */
    public function getThermostatInfo(): array
    {
        return $this->toonApi->getThermostatInfo();
    }

    /**
     * Set the target temperature.
     *
     * @param float $temperature
     * @return array
     */
    public function setTemperature(float $temperature): array
    {
        return $this->toonApi->setTemperature($temperature);
    }

    /**
     * Get current energy usage data.
     *
     * @return array
     */
    public function getEnergyUsage(): array
    {
        return $this->toonApi->getEnergyUsage();
    }

    /**
     * Get energy usage for a specific period.
     *
     * @param string $period ('day', 'week', 'month', 'year')
     * @return array
     */
    public function getEnergyUsageByPeriod(string $period): array
    {
        return $this->toonApi->getEnergyUsageByPeriod($period);
    }

    /**
     * Get all agreements/contracts.
     *
     * @return array
     */
    public function getAgreements(): array
    {
        return $this->toonApi->getAgreements();
    }

    /**
     * Get device information.
     *
     * @return array
     */
    public function getDeviceInfo(): array
    {
        return $this->toonApi->getDeviceInfo();
    }

    /**
     * Get status of all connected devices.
     *
     * @return Collection
     */
    public function getAllDevicesStatus(): Collection
    {
        $devices = $this->toonApi->getDeviceStatus();
        return collect($devices);
    }

    /**
     * Set thermostat program/schedule.
     *
     * @param int $programId
     * @return array
     */
    public function setProgram(int $programId): array
    {
        return $this->toonApi->setProgram($programId);
    }

    /**
     * Get current thermostat program/schedule.
     *
     * @return array
     */
    public function getCurrentProgram(): array
    {
        return $this->toonApi->getCurrentProgram();
    }

    /**
     * Get all available programs/schedules.
     *
     * @return Collection
     */
    public function getPrograms(): Collection
    {
        $programs = $this->toonApi->getPrograms();
        return collect($programs);
    }

    /**
     * Get smart plug status and energy data.
     *
     * @param string $deviceId
     * @return array
     */
    public function getSmartPlugData(string $deviceId): array
    {
        return $this->toonApi->getSmartPlugData($deviceId);
    }

    /**
     * Turn smart plug on/off.
     *
     * @param string $deviceId
     * @param bool $state
     * @return array
     */
    public function setSmartPlugState(string $deviceId, bool $state): array
    {
        return $this->toonApi->setSmartPlugState($deviceId, $state);
    }

    /**
     * Get solar panel data if available.
     *
     * @return array
     */
    public function getSolarData(): array
    {
        return $this->toonApi->getSolarData();
    }

    /**
     * Get gas usage information.
     *
     * @return array
     */
    public function getGasUsage(): array
    {
        return $this->toonApi->getGasUsage();
    }

    /**
     * Check if Toon service is available/reachable.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        try {
            $this->toonApi->getDeviceInfo();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get comprehensive dashboard data.
     *
     * @return array
     */
    public function getDashboardData(): array
    {
        return [
            'thermostat' => $this->getThermostatInfo(),
            'energy_usage' => $this->getEnergyUsage(),
            'gas_usage' => $this->getGasUsage(),
            'device_info' => $this->getDeviceInfo(),
            'current_program' => $this->getCurrentProgram(),
        ];
    }
}