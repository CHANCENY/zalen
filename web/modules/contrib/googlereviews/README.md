# Google reviews Drupal module
This module provides two blocks to show Google reviews and rating on your
website.

## Rating block
The Google reviews rating block show the average rating made by all users
including stars. The block also includes a link to the reviews on Google.

## Reviews list block
The reviews list block shows a maximum of 5 reviews with the author name,
author profile picture, a link to all reviews by this user and
the rating and description of the review.
In the block settings it's possible to change the amount of reviews to show and
what the sorting should be; most relevant to Google or the newest reviews.

## Theming
The two blocks are fully themeable through the supplied twig templates and basic
css files. We also provided a colored Google logo as svg in the images folder.

## Installation
Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

## Requirements

This module requires no modules outside of Drupal core.

## Configuration

After installing go to the Google review settings page
(admin/config/system/googlereviews) and enter your Google Maps API key and
the Place ID from the location from which you want to show the reviews.

You need to create a free account at Google and register a project to be able
to obtain an Google Maps API key. Documentation about this process can be found
at [Google Maps developer documentation](https://developers.google.com/maps/documentation/embed/get-api-key).

To find the place_id belonging to the location you want to show the reviews of
go to the [Google Places Place ID finder](https://developers.google.com/maps/documentation/javascript/examples/places-placeid-finder).

The Reviews list block has some settings concerning sorting and amount of reviews.

## Maintainers

- Peter van der West - [ptitb](https://www.drupal.org/u/ptitb)
