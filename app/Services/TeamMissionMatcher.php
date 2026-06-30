<?php
/**
 * SynDrasi - Scores team readiness against mission requirements.
 */
class TeamMissionMatcher
{
    private const PEOPLE_WEIGHT = 30;
    private const VEHICLE_WEIGHT = 15;
    private const MEDICAL_WEIGHT = 15;
    private const ITEMS_WEIGHT = 40;

    public static function scoreTeam(array $event, array $team, ?array $application = null): array
    {
        $requiredPeople = max(0, (int) ($event['requested_people'] ?? 0));
        $availablePeople = $application
            ? max(0, (int) ($application['offered_people'] ?? 0))
            : max(0, (int) ($team['default_people_capacity'] ?? 0));

        $score = 0.0;
        $missing = [];
        $matched = [];

        if ($requiredPeople <= 0) {
            $score += self::PEOPLE_WEIGHT;
        } else {
            $ratio = min(1, $availablePeople / $requiredPeople);
            $score += self::PEOPLE_WEIGHT * $ratio;
            if ($availablePeople < $requiredPeople) {
                $missing[] = ($requiredPeople - $availablePeople) . ' άτομα';
            } else {
                $matched[] = 'Άτομα';
            }
        }

        $needsVehicle = !empty($event['requested_vehicle']);
        $hasVehicle = $application
            ? !empty($application['offered_vehicle'])
            : !empty($team['has_vehicle']);
        if (!$needsVehicle || $hasVehicle) {
            $score += self::VEHICLE_WEIGHT;
            if ($needsVehicle) {
                $matched[] = 'Όχημα';
            }
        } else {
            $missing[] = 'Όχημα';
        }

        $needsMedical = !empty($event['requested_medical_equipment']);
        $hasMedical = $application
            ? !empty($application['offered_medical_equipment'])
            : !empty($team['has_medical_equipment']);
        if (!$needsMedical || $hasMedical) {
            $score += self::MEDICAL_WEIGHT;
            if ($needsMedical) {
                $matched[] = 'Υγειονομικός εξοπλισμός';
            }
        } else {
            $missing[] = 'Υγειονομικός εξοπλισμός';
        }

        $requiredItems = self::eventRequestedItems($event);
        $teamItems = VolunteerTeam::readinessItems($team);
        $teamLookup = self::lookup($teamItems);
        $itemMatches = [];
        $itemMissing = [];

        foreach ($requiredItems as $item) {
            $key = self::key($item);
            if (isset($teamLookup[$key])) {
                $itemMatches[] = $item;
            } else {
                $itemMissing[] = $item;
            }
        }

        if (!$requiredItems) {
            $score += self::ITEMS_WEIGHT;
        } else {
            $score += self::ITEMS_WEIGHT * (count($itemMatches) / count($requiredItems));
            foreach ($itemMatches as $item) {
                $matched[] = $item;
            }
            foreach ($itemMissing as $item) {
                $missing[] = $item;
            }
        }

        $score = (int) round(min(100, max(0, $score)));
        return [
            'score'          => $score,
            'level'          => self::level($score),
            'level_class'    => self::levelClass($score),
            'matched'        => array_values(array_unique($matched)),
            'missing'        => array_values(array_unique($missing)),
            'required_items' => $requiredItems,
            'team_items'     => $teamItems,
        ];
    }

    public static function rankedTeamsForEvent(array $event, array $teams): array
    {
        $out = [];
        foreach ($teams as $team) {
            $team['match'] = self::scoreTeam($event, $team);
            $out[] = $team;
        }
        usort($out, function ($a, $b) {
            return ($b['match']['score'] <=> $a['match']['score']) ?: strcmp((string) $a['name'], (string) $b['name']);
        });
        return $out;
    }

    private static function eventRequestedItems(array $event): array
    {
        $decoded = json_decode((string) ($event['requested_items_json'] ?? ''), true);
        if (is_array($decoded) && $decoded) {
            return self::cleanList($decoded);
        }

        $playbook = EventPlaybook::forEvent($event);
        if ($playbook && !empty($playbook['requested_items'])) {
            return self::cleanList($playbook['requested_items']);
        }
        return [];
    }

    private static function cleanList(array $items): array
    {
        $out = [];
        $seen = [];
        foreach ($items as $item) {
            $item = preg_replace('/\s+/', ' ', trim((string) $item));
            if ($item === '') {
                continue;
            }
            $key = self::key($item);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $out[] = $item;
            }
        }
        return $out;
    }

    private static function lookup(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            $out[self::key($item)] = true;
        }
        return $out;
    }

    private static function key(string $item): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim($item)), 'UTF-8');
    }

    private static function level(int $score): string
    {
        if ($score >= 85) {
            return 'Ισχυρό match';
        }
        if ($score >= 65) {
            return 'Καλό match';
        }
        if ($score >= 40) {
            return 'Μερικό match';
        }
        return 'Χαμηλό match';
    }

    private static function levelClass(int $score): string
    {
        if ($score >= 85) {
            return 'success';
        }
        if ($score >= 65) {
            return 'primary';
        }
        if ($score >= 40) {
            return 'warning';
        }
        return 'danger';
    }
}
