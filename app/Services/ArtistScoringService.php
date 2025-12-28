<?php

namespace App\Services;

use App\Models\Artist;
use Illuminate\Support\Facades\Config;

/**
 * Service for calculating artist scores based on metrics.
 *
 * Implements logarithmic normalization and weighted averages.
 */
class ArtistScoringService
{
    /**
     * Calculate the final score for an artist (0-100).
     *
     * For now, this uses the 'balanced' preset from config as a default.
     * In the future, this will take an Organization model and use its specific weights.
     */
    public function calculateScore(Artist $artist): int
    {
        $metrics = $artist->metrics;
        if (! $metrics) {
            return 0;
        }

        $weights = Config::get('artist-tree.metric_presets.balanced');
        $score = 0;

        foreach ($weights as $metricName => $weight) {
            $value = $this->getMetricValue($artist, $metricName);
            $normalized = $this->normalizeLogarithmic($value, $metricName);
            $score += $normalized * $weight;
        }

        return (int) round($score);
    }

    /**
     * Calculate score from a raw array of metrics.
     *
     * @param  array<string, float|int|null>  $metrics
     */
    public function calculateScoreFromMetrics(array $metrics): int
    {
        $weights = Config::get('artist-tree.metric_presets.balanced');
        $score = 0;

        foreach ($weights as $metricName => $weight) {
            $value = $this->getMetricValueFromArray($metrics, $metricName);
            $normalized = $this->normalizeLogarithmic($value, $metricName);
            $score += $normalized * $weight;
        }

        return (int) round($score);
    }

    /**
     * Get the raw value for a metric from the artist or its metrics model.
     */
    private function getMetricValue(Artist $artist, string $metricName): float
    {
        $metrics = $artist->metrics;
        if (! $metrics) {
            return 0.0;
        }

        // Map config metric names to model attributes
        $mapping = [
            'spotify_monthly_listeners' => 'spotify_followers', // Fallback as we don't have monthly listeners yet
            'spotify_popularity' => 'spotify_popularity',
            'youtube_subscribers' => 'youtube_subscribers',
            'spotify_followers' => 'spotify_followers',
            'instagram_followers' => 'instagram_followers',
        ];

        $attribute = $mapping[$metricName] ?? $metricName;

        return (float) ($metrics->{$attribute} ?? 0.0);
    }

    /**
     * Normalize a metric value using a logarithmic scale (0-100).
     */
    private function normalizeLogarithmic(float $value, string $metricName): float
    {
        // Special case: spotify_popularity is already 0-100
        if ($metricName === 'spotify_popularity') {
            return min(max($value, 0), 100);
        }

        $maxValues = Config::get('artist-tree.normalization_max');
        $max = $maxValues[$metricName] ?? 100000000;

        if ($value <= 0) {
            return 0;
        }

        // Formula: (log10(value + 1) / log10(max)) * 100
        $normalized = (log10($value + 1) / log10($max)) * 100;

        return min(max($normalized, 0), 100);
    }

    /**
     * Get the raw value for a metric from a metrics array.
     */
    private function getMetricValueFromArray(array $metrics, string $metricName): float
    {
        // Map config metric names to array keys
        $mapping = [
            'spotify_monthly_listeners' => 'spotify_followers', // Fallback
            'spotify_popularity' => 'spotify_popularity',
            'youtube_subscribers' => 'youtube_subscribers',
            'spotify_followers' => 'spotify_followers',
            'instagram_followers' => 'instagram_followers',
        ];

        $key = $mapping[$metricName] ?? $metricName;

        return (float) ($metrics[$key] ?? 0.0);
    }
}
