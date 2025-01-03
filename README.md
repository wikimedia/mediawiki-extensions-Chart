This is a MediaWiki extension for displaying charts.

For more information, see https://www.mediawiki.org/wiki/Extension:Chart

== Usage ==

Install, compile the service, and configure the Data: namespace per notes below.

Charts are expected to be configured on `.chart` pages in the Data: namespace, and
render using data from tabular `.tab` pages also in the Data: namespace.

Sample usage invoking both explicitly:

```
{{#chart:1993 Canadian federal election.chart|data=1993 Canadian federal election.tab}}
```

== Installation ==
- Install the JsonConfig extension; see https://www.mediawiki.org/wiki/Extension:JsonConfig#Installation
- Clone this repo into the `extensions/Chart` directory in your MediaWiki installation
- Clone the `chart-renderer` repo from https://gitlab.wikimedia.org/repos/mediawiki/services/chart-renderer.git
  into the `extensions/Chart/chart-renderer` directory
- In the `chart-renderer` directory, run `npm install` and `npm run build`
- Add `wfLoadExtension( 'Chart' )` at the bottom of `LocalSettings.php`

== Additional setup steps for MediaWiki-Docker ==
If you're using MediaWiki-Docker, add the following to the `docker-compose.override.yml` file in the
MediaWiki directory:
```
services:
  mediawiki:
    build:
      context: ./extensions/Chart
      dockerfile: nodejs.dockerfile
```

Then run:
```
docker compose build
docker compose down
docker compose up -d
```

== CLI renderer or HTTP service ==
By default, the Chart extension renders charts by shelling out to the `cli.js` script in the
`chart-renderer` directory. This is the recommended setup for local development.

If you want to shell out to the CLI renderer, but the `chart-renderer` directory is in a different
location, customize the path to the `cli.js` script by adding the following to `LocalSettings.php`:
```
$wgChartCliPath = 'path/to/chart-renderer/cli.js';
```

In production environments, using the HTTP service is recommended. To set up the HTTP service,
clone the `chart-renderer` directory, run `npm install`, then run `npm start` to start the service.
Then point the Chart extension to the service by adding the following to `LocalSettings.php`:
```
$wgChartServiceUrl = 'http://localhost:6284/v1/chart/render';
```
If the chart-renderer service runs on a different machine than the PHP web server, adjust the
`$wgChartServiceUrl` setting accordingly.

== JsonConfig dependency ==

For the local `Data:` namespace, the `JsonConfig` extension is required to be running
and configured for "`.tab`" tabular data and "`.chart`" formatting definitions.

In a wiki farm configuration with multiple wikis pointing to a central repository of `Data:` pages, cache invalidation of pages using a chart definition or tabular data set will be performed via the global JSON tracking in `JsonConfig`.

=== .tab tabular data ===

The "`.tab`" data pages are as per https://www.mediawiki.org/wiki/Help:Tabular_Data

Sample config for local development:

```php
	// Tabular data is supported, but not configured on by default, in JsonConfig.
	// This should be simplified.
	// https://www.mediawiki.org/wiki/Extension:JsonConfig#Configuration
	$wgJsonConfigModels['Tabular.JsonConfig'] = 'JsonConfig\JCTabularContent';
	$wgJsonConfigs['Tabular.JsonConfig'] = [
		'namespace' => 486,
		'nsName' => 'Data',
		// page name must end in ".tab", and contain at least one symbol
		'pattern' => '/.\.tab$/',
		'license' => 'CC0-1.0',
		'isLocal' => true,
	];
```

If you want to host the Data namespace on a single, central wiki that other wikis pull from,
add the following after the configuration above:
```php
	$wgJsonConfigs['Tabular.JsonConfig']['isLocal'] = false;

	if ( $wgDBname === 'my_central_wiki' ) {
		$wgJsonConfigs['Tabular.JsonConfig']['store'] = true;
	} else {
		$wgJsonConfigs['Tabular.JsonConfig']['remote'] = [
			'url' => 'https://my-central-wiki.example.com/w/api.php'
		];
	}
```

A sample `.tab` page may be found in the `sample/` folder.

=== .chart format descriptions ===

The "`.chart`" data pages are custom for this extension and also build on `JsonConfig`.

```php
	// Chart data is supported here in the Chart extension, and currently must be
	// configured manually as well. This should be simplified.
	// https://www.mediawiki.org/wiki/Extension:JsonConfig#Configuration
	$wgJsonConfigModels['Chart.JsonConfig'] = 'MediaWiki\Extension\Chart\JCChartContent';
	$wgJsonConfigs['Chart.JsonConfig'] = [
		'namespace' => 486,
		'nsName' => 'Data',
		// page name must end in ".chart", and contain at least one symbol
		'pattern' => '/.\.chart$/',
		'license' => 'CC0-1.0',
		'isLocal' => true,
	];
```

If you want to host the Data namespace on a single, central wiki that other wikis pull from,
add the following after the configuration above:
```php
	$wgJsonConfigs['Chart.JsonConfig']['isLocal'] = false;

	if ( $wgDBname === 'my_central_wiki' ) {
		$wgJsonConfigs['Chart.JsonConfig']['store'] = true;
	} else {
		$wgJsonConfigs['Chart.JsonConfig']['remote'] = [
			'url' => 'https://my-central-wiki.example.com/w/api.php'
		];
	}
```

Sample `.chart` page may be found under the `sample/` folder.
