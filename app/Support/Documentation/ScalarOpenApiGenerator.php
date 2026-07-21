<?php

declare(strict_types=1);

namespace App\Support\Documentation;

use Knuckles\Camel\Extraction\Metadata;
use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Scribe\Writing\OpenApiSpecGenerators\OpenApiGenerator;

final class ScalarOpenApiGenerator extends OpenApiGenerator
{
    public function root(array $root, array $groupedEndpoints): array
    {
        /** @see https://github.com/scalar/scalar/blob/main/packages/types/src/api-reference/api-reference-configuration.ts#L225 */
        $scalarConfig = [
            'data-configuration' => htmlspecialchars(
                json_encode([
                    'theme' => 'deepSpace',
                    'hideClientButton' => true,
                    'hideDarkModeToggle' => true,
                    'defaultHttpClient' => [
                        'targetKey' => 'js',
                        'clientKey' => 'fetch',
                    ],
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ENT_QUOTES
            ),
        ];
        $this->config->data['external']['html_attributes'] = $scalarConfig;

        $tags = [];
        $tagsHashmap = [];

        foreach ($groupedEndpoints as $groupedEndpoint) {
            $currentGroupTags = [
                'name' => $groupedEndpoint['name'],
            ];
            $grouped = [];

            foreach ($groupedEndpoint['endpoints'] as $endpoint) {
                /** @var Metadata $metadata */
                $metadata = $endpoint['metadata'];

                // Only process endpoints that have both group and subgroup
                if (! $metadata->groupName || ! $metadata->subgroup) {
                    continue; // These will go to "Other > Ungrouped"
                }

                $tagName = self::generateTagNameFromMetadata($metadata);

                if (isset($tagsHashmap[$tagName])) {
                    continue;
                }

                $tagsHashmap[$tagName] = 1;
                $tagGroup = [
                    'name' => $tagName,
                    'x-displayName' => $metadata->subgroup,
                    'description' => $metadata->subgroupDescription,
                ];

                $tags[] = $tagGroup;
                $grouped[] = $tagGroup['name'];
            }

            // Only add the group if it has any properly tagged endpoints
            if (! empty($grouped)) {
                sort($grouped, SORT_STRING);
                $currentGroupTags['tags'] = $grouped;
                $root['x-tagGroups'][] = $currentGroupTags;
            }
        }

        // Set default tag for endpoints with no group at all
        $defaultTag = 'Other_Ungrouped';
        if (! isset($tagsHashmap[$defaultTag])) {
            $tags[] = [
                'name' => $defaultTag,
                'x-displayName' => 'Ungrouped',
                'description' => 'Endpoints without a specific group or subgroup',
            ];
            $tagsHashmap[$defaultTag] = 1;

            // Add to x-tagGroups under "Other"
            $root['x-tagGroups'][] = [
                'name' => 'Other',
                'tags' => [$defaultTag],
            ];
        }

        $root['tags'] = $tags;

        return $root;
    }

    public function pathItem(array $pathItem, array $groupedEndpoints, OutputEndpointData $endpoint): array
    {
        /** @var Metadata $metadata */
        $metadata = $endpoint['metadata'];

        // If endpoint has both group and subgroup, use normal tag generation
        if ($metadata->groupName && $metadata->subgroup) {
            $tagName = self::generateTagNameFromMetadata($metadata);
        }
        // If endpoint is missing either group or subgroup, place under "Other > Ungrouped"
        else {
            $tagName = 'Other_Ungrouped';
        }

        $pathItem['tags'] = [$tagName];

        return $pathItem;
    }

    private static function generateTagNameFromMetadata(Metadata $metadata): string
    {
        $name = $metadata->groupName;
        $name .= $metadata->subgroup ? "_{$metadata->subgroup}" : '';

        return str_replace(' ', '_', $name);
    }
}
