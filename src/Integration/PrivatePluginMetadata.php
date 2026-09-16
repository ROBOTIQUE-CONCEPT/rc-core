<?php

declare(strict_types=1);

namespace WPRC\Core\Integration;

defined('ABSPATH') || exit;

/**
 * Provides local WordPress.org-style metadata for the private RC Core plugin.
 *
 * WordPress' native plugin-dependency system queries the WordPress.org Plugins
 * API for dependencies declared through the `Requires Plugins` header. RC Core
 * is private, so this class short-circuits that lookup and returns the metadata
 * locally instead of allowing an unnecessary HTTP 404 request.
 */
final class PrivatePluginMetadata
{
    private const PLUGIN_SLUG = 'rc-core';

    /**
     * Register the Plugin API override as early as possible.
     */
    public static function register(): void
    {
        add_filter('plugins_api', [self::class, 'filterPluginInformation'], 10, 3);
    }

    /**
     * Return local metadata for RC Core plugin information requests.
     *
     * @param false|object|array $result Existing Plugin API result.
     * @param string             $action Requested Plugin API action.
     * @param object             $args   Plugin API request arguments.
     * @return false|object|array
     */
    public static function filterPluginInformation($result, string $action, object $args)
    {
        if (
            'plugin_information' !== $action
            || empty($args->slug)
            || self::PLUGIN_SLUG !== (string) $args->slug
        ) {
            return $result;
        }

        return (object) [
            'name'              => 'RC Core',
            'slug'              => self::PLUGIN_SLUG,
            'version'           => RC_CORE_VERSION,
            'author'            => 'Robotique Concept',
            'author_profile'    => 'https://www.robotiqueconcept.com/',
            'homepage'          => 'https://www.robotiqueconcept.com/',
            'requires'          => '6.8',
            'tested'            => wp_get_wp_version(),
            'requires_php'      => '8.1',
            'short_description' => 'Socle technique partagé, connecteurs, cache, journalisation et contrats pour les extensions Robotique Concept.',
            'sections'          => [
                'description' => 'Extension privée fournissant l’infrastructure technique des applications WordPress Robotique Concept.',
            ],
            'icons'             => [],
            'last_updated'      => gmdate('Y-m-d H:i:s'),
            'external'          => true,
        ];
    }
}
