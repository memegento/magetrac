#!/usr/bin/env php
<?php
// Whe know it could be better but
// @see https://github.com/qossmic/deptrac/issues/978#issuecomment-1241704775
$appCodeDir = $argv[1] ?? null;

$allModulesXmlFiles = findModules($appCodeDir);
$layers = '';
$rules = [];
$allModules = [];

foreach ($allModulesXmlFiles as $modulesXmlFile) {
    $xml = simplexml_load_string(file_get_contents($modulesXmlFile));
    $array = json_decode(json_encode($xml), true);

    $currentLayerName = $array['module']['@attributes']['name'];
    $deps = array_map(function ($dependency) {
        return $dependency['@attributes']['name'] ?? null;
    }, $array['module']['sequence']['module'] ?? []);

    list($vendor, $module) = explode('_', $currentLayerName);
    $collectorRegex = $vendor . '\\\\' . $module . '\\\\' . '.*';
    $allModules[$currentLayerName] = $modulesXmlFile;
    $layers .= <<<LAYER
- name: {$currentLayerName}
      collectors:
        - type: className
          value: {$collectorRegex}
LAYER. "\n    ";

    foreach ($deps as $dependency) {
        if (!$dependency) {
            continue;
        }
        $rules[$currentLayerName][] = $dependency;
    }
}

$ruleset = '';

foreach ($rules as $layer => $layerDeps) {
    $allowedDeps = implode(" ", $layerDeps);

    $ruleset .= <<<RULESET
{$layer}: [{$allowedDeps}]
RULESET. "\n    ";
}

$finalConfig = <<<CONFIG
#This file is generated automatically by `./magetrac.php $appCodeDir` for all magento modules
deptrac:
  layers:
    {$layers}
  ruleset:
    {$ruleset}
CONFIG;

file_put_contents('./deptrac.layers.yaml', $finalConfig);

echo "\n Config written to ./deptrac.layers.yaml \n";
echo "\n Now run ./deptrac \n";

//functions:
function findModules(string $dirToScan): array
{
    $modules = glob(
        sprintf(
            '%s/*/*/etc/module.xml',
            rtrim($dirToScan, '/')
        )
    );

    if (!count($modules)) {
        throw new \RuntimeException(
            sprintf(
                'No Magento 2 modules found in given ./app/code directory. Make sure the directory "%s" is proper ./app/code in your project',
                $dirToScan
            )
        );
    }

    return $modules;
}
