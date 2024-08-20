## INTRODUCTION

The OpenWeather API module is implements the OpenWeather One Call API 3.0 as a Service. By itself, this module does nothing
and other modules should use it to connect and query the One Call API.

Once complete, this module will be moved to Drupal.org as a community module. Currently, there are no
tests which is recommended for contrib modules.

## REQUIREMENTS

There are no other module dependencies for this module.

You will need an API key from [openweathermap.org](https://openweathermap.org) to access the API.

## INSTALLATION

The recommended method of installation is using composer. Before you can add this module to your project,
you will need to first add this repository to your composer.json file under the "repositories" section. The code below
demonstrates how to do this.

```javascript
"repositories": [
    ... ,
    {
        "type": "vcs",
        "url": "https://github.com/r0nn1ef/ow_onecall.git"
    }
]
```

Once you have the repository added, you can add the module using <code>composer require drupal/ow_onecall:@beta</code>

## CONFIGURATION
- To enter your API key, navigate to /admin/config/services/openweather.

## ROADMAP
- [x] Make api key optionally stored in either the database or in a .evn file. :tada:

## MAINTAINERS

Current maintainers for Drupal 10 and 11:

- Ron Ferguson (r0nn1ef) - https://www.drupal.org/u/r0nn1ef

