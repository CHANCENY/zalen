# Datetime Flatpickr

This module provides a widget for the Date and Date range field types that 
uses the flatpickr javascript library to make picking dates easier. It allows 
for more configurability in selecting the date format and takes the flatpickr 
library from a CDN.

## Introduction

The Datetime Flatpickr module adds the flatpickr javascript library to the 
Drupal datetime field. This improves the user experience by making it easier 
to pick and format dates. The module uses the flatpickr library from a CDN, 
which eliminates the need for users to manually download and install the 
library.

## Requirements

- Flatpickr javascript library

## Installation

1. Install the module as usual, see [Installing modules](https://www.drupal.org/docs/extending-drupal/installing-modules) 
for further information.
2. [Optional] By default, the library is loaded via CDN, but it is possible
to load the library locally by editing the main composer.json file.

Add the following entry in the "repositories" section of your main composer.json file.
```
    {
        "type": "package",
        "package": {
            "name": "flatpickr/flatpickr",
            "version": "4.6.13",
            "type": "drupal-library",
            "dist": {
                "url": "https://registry.npmjs.org/flatpickr/-/flatpickr-4.6.13.tgz",
                "type": "tar"
            }
        }
    },
```

Now you can run the following command to install flatpickr in the right folder:

```
composer require flatpickr/flatpickr:4.6.13
```

## Configuration

1. Go to the `Manage form display` page for the content type you want to add 
the widget to.
2. Locate the datetime field and change the widget to 
`Flatpickr datetime picker`.
3. Save the configuration.

## Troubleshooting & FAQ

**Q: I am facing an issue where the default value is not correct when editing 
an entity. The date format is set to for example d/m/Y, but the date display 
is incorrect when editing the entity. How can I fix this?**

A: This issue can occur when the input field receives a value from the 
database, but the "Date format" option used by the flatpickr library is 
different from the date format of the field. Fortunately, the flatpickr 
library includes the `altFormat` option, which allows you to handle the 
right date format and still display something different to the user. To 
solve this issue, you can use the "Alternative format" option in the 
widget's configuration.
