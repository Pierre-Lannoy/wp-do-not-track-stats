=== Do Not Track Stats ===
Contributors: PierreLannoy
Tags: dnt, donottrack, privacy, gdpr, analytics
Requires at least: 4.9
Tested up to: 5.2
Requires PHP: 7.1
Stable tag: 1.1.6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://support.laquadrature.net/

Easily obtain reliable statistics on the use of the "Do Not Track" policy by your visitors.

== Description ==
Do Not Track Stats performs an analysis of the HTTP headers received by your website - while respecting the privacy choices made by your visitors - to compile statistical measurements about the use of the "Do Not Track" policy.

This policy signal is sent to your site by some of your visitors, indicating that they do not want to be tracked.
For each request received by your site, if it is not excluded by your settings, Do Not Track Stats checks the header and stores the presence or absence of this signal.
The possible values are:

* unset: the visitor did not specify anything
* consent (opt in): the visitor has explicitly consented to the tracking
* opposition (opt out): the visitor explicitly opposes tracking - if you want to comply with the GDPR, you must act accordingly to that non-consent

Of course, Do Not Track Stats respects the privacy choices made by your visitors: it doesn't handle or store confidential data (like names, ip addresses, etc.) nor does it use any intrusive technology for privacy (like cookies).

Do Not Track Stats is fully integrated with [oEmbed Manager](https://wordpress.org/plugins/oembed-manager/) to allow you to block the display of embedded content when the visitor explicitly opposes tracking.

For more details on the "Do Not Track" policy, you can consult [the wikipedia page devoted to it](https://en.wikipedia.org/wiki/Do_Not_Track), or [the Electronic Frontier Foundation website](https://www.eff.org/issues/do-not-track).

== Installation ==

= From your WordPress dashboard =

1. Visit 'Plugins > Add New'.
2. Search for 'Do Not Track Stats'.
3. Click on the 'Install Now' button.
4. Activate Do Not Track Stats.

= From WordPress.org =

1. Download Do Not Track Stats.
2. Upload the `do-not-track-stats` directory to your `/wp-content/plugins/` directory, using your favorite method (ftp, sftp, scp, etc...).
3. Activate Do Not Track Stats from your Plugins page.

= Once Activated =

1. Visit 'DNT Stats' in the 'Settings' menu of your WP Admin to adjust settings and 'DNT Stats' in the 'Tools' menu to get statistics.
2. Enjoy!

== Frequently Asked Questions ==

= What are the requirements for this plugin to work? =

You need **WordPress 4.9** and at least **PHP 7.1**.

= Can this plugin work on multisite? =

Yes. You can install it via the network admin plugins page but the plugin **must not be "Network Activated"**, instead you must activate it on a site by site basis.

= Where can I get support? =

Support is provided via the official [support page](https://wordpress.org/support/plugin/do-not-track-stats).

= Where can I report a bug? =

You can report bugs and suggest ideas via the official [support page](https://wordpress.org/support/plugin/do-not-track-stats).

== Changelog ==

= 1.1.6 =

Release Date: April 28th, 2019

* Improvement: WordPress 5.2 compatibility.

= 1.1.5 =

Release Date: February 25th, 2019

* Improvement: WordPress 5.1 compatibility.

= 1.1.4 =

Release Date: December 24th, 2018

* Improvement: many new agent strings.

= 1.1.3 =

Release Date: November 2nd, 2018

* Improvement: full compatibility with WordPress 5.0.

= 1.1.2 =

Release Date: October 12th, 2018

* Improvement: percentages in raw data table.

= 1.1.1 =

Release Date: September 18th, 2018

* Improvement: ajax filtering is now mandatory.

= 1.1.0 =

Release Date: August 16th, 2018

* New: integration with *oEmbed Manager* plugin.
* Improvement: better bot handling for exclusion rules.

= 1.0.4 =

Release Date: July 25th, 2018

* Bug fix: the raw data table contains malformed HTML.

= 1.0.3 =

Release Date: July 12th, 2018

* Bug fix: opt-in and opt-out are inverted in the raw data table.
* Bug fix: the button for refreshing data is not translatable (thanks to [Laurent Naudier](https://twitter.com/laurent_naudier) for pointing it).

= 1.0.2 =

Release Date: July 12th, 2018

* Improvement: the plugin defines now a constant (in the `init` hook) named DO_NOT_TRACK_STATUS.
* Bug fix: some typos in admin panel.

= 1.0.1 =

Release Date: July 12th, 2018

* Improvement: settings strings are more accurate.
* Improvement: all strings are now translatable.
* Bug fix: deprecated warning in smoke test.
* Bug fix: some typos in `readme.txt` file.

= 1.0.0 =

Release Date: July 11th, 2018

* First public version

== Upgrade Notice ==

= 1.0.X =
Initial version.

== Screenshots ==

1. Statistics view
2. Settings
