<?php

namespace App\Services;

use App\Models\LearningNeedsAnalysis;

class StaticLnaAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function recommendation(LearningNeedsAnalysis $entry): array
    {
        $profile = $this->profile($entry);
        $priorityLabel = ucfirst((string) $entry->priority_level);

        return [
            'lna_id' => $entry->id,
            'focus_area' => $entry->focus_area,
            'priority_level' => $entry->priority_level,
            'predictive_skills_gap' => $entry->predictive_skills_gap,
            'prescriptive_training_recommendation' => $entry->prescriptive_training_recommendation,
            'training_type' => $profile['training_type'],
            'provider' => $profile['provider'],
            'track' => $profile['category'],
            'rationale' => "{$priorityLabel} priority development in {$entry->focus_area} indicates a need for {$profile['category']}.",
        ];
    }

    /**
     * Generate static analytics after both employee and supervisor assessments exist.
     *
     * @return array{predictive_skills_gap: string, prescriptive_training_recommendation: string}
     */
    public function generate(LearningNeedsAnalysis $entry): array
    {
        $profile = $this->profile($entry);
        $employeeRatings = collect($entry->skill_assessments ?? []);
        $supervisorRatings = collect($entry->supervisor_skill_assessments ?? []);
        $lowRatedSkills = $employeeRatings
            ->merge($supervisorRatings)
            ->filter(fn (string $rating): bool => in_array($rating, ['1', '2'], true))
            ->keys()
            ->unique()
            ->values();

        return [
            'predictive_skills_gap' => $lowRatedSkills->isEmpty()
                ? $profile['skills_gap']
                : $lowRatedSkills->implode(', '),
            'prescriptive_training_recommendation' => $profile['training'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function profile(LearningNeedsAnalysis $entry): array
    {
        $context = strtolower(trim($entry->focus_area.' '.$entry->competency_gap.' '.$entry->proposed_intervention));

        return match (true) {
            str_contains($context, 'lead') || str_contains($context, 'supervis') => [
                'category' => 'Leadership and Supervision',
                'skills_gap' => 'Leadership, coaching, delegation, and team supervision',
                'training' => 'Supervisory Development Program',
                'training_type' => 'In-house',
                'provider' => 'HRDC Learning and Development Unit',
                'delivery' => 'In-house workshop with guided coaching',
                'timeframe' => 'Within the next 3 months',
            ],
            str_contains($context, 'data') || str_contains($context, 'excel') || str_contains($context, 'digital') || str_contains($context, 'system') => [
                'category' => 'Digital Productivity and Data Management',
                'skills_gap' => 'Digital tools, records management, and data analysis',
                'training' => 'Digital Productivity and Data Management Workshop',
                'training_type' => 'Invitational',
                'provider' => 'Civil Service Commission / External ICT Partner',
                'delivery' => 'Hands-on workshop and workplace application',
                'timeframe' => 'Within the next 2 months',
            ],
            str_contains($context, 'commun') || str_contains($context, 'writing') || str_contains($context, 'report') || str_contains($context, 'present') => [
                'category' => 'Communication and Technical Writing',
                'skills_gap' => 'Written communication, presentation, and report preparation',
                'training' => 'Technical Writing and Presentation Skills Training',
                'training_type' => 'In-house',
                'provider' => 'Secretariat and HRDC',
                'delivery' => 'Instructor-led training with output review',
                'timeframe' => 'Within the next 3 months',
            ],
            str_contains($context, 'customer') || str_contains($context, 'client') || str_contains($context, 'service') => [
                'category' => 'Customer Service Excellence',
                'skills_gap' => 'Client handling, service delivery, and stakeholder engagement',
                'training' => 'Customer Service Excellence Program',
                'training_type' => 'In-house',
                'provider' => 'HRDC Service Quality Team',
                'delivery' => 'Scenario-based workshop and coaching',
                'timeframe' => 'Within the next 2 months',
            ],
            str_contains($context, 'plan') || str_contains($context, 'project') || str_contains($context, 'monitor') => [
                'category' => 'Planning and Project Monitoring',
                'skills_gap' => 'Planning, implementation monitoring, and target management',
                'training' => 'Project Planning and Monitoring Workshop',
                'training_type' => 'Invitational',
                'provider' => 'DILG / Accredited Training Provider',
                'delivery' => 'Workshop with an applied project plan',
                'timeframe' => 'Within the next 3 months',
            ],
            default => [
                'category' => 'Core Functional Capability',
                'skills_gap' => 'Role-specific functional competency requiring development',
                'training' => 'Functional Competency Enhancement Training',
                'training_type' => 'In-house',
                'provider' => 'HRDC Learning and Development Unit',
                'delivery' => 'In-house training with supervisor coaching',
                'timeframe' => 'Within the next 6 months',
            ],
        };
    }
}
