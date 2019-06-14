# Do Not Track Stats

**Do Not Track Stats** is a WordPress plugin to perform an analysis of the HTTP headers received by your website to compile statistical measurements about the use of the “Do Not Track” policy.

The “Do Not Track” policy signal is sent to your site by some of your visitors, indicating that they do not want to be tracked.

For each request received by your site, if it is not excluded by your settings, **Do Not Track Stats** checks the header and stores the presence or absence of this signal.

**Do Not Track Stats** respects the privacy choices made by your visitors: it doesn’t handle or store confidential data (like names, ip addresses, etc.) nor does it use any intrusive technology for privacy (like cookies).

Do Not Track Stats is fully integrated with oEmbed Manager to allow you to block the display of embedded content when the visitor explicitly opposes tracking.

See [WordPress directory page](https://wordpress.org/plugins/do-not-track-stats/).

## Installation

### WordPress method (recommended)

1. From your WordPress dashboard, visit _Plugins | Add New_.
2. Search for 'Do Not Track Stats'.
3. Click on the 'Install Now' button.

You can now activate **Do Not Track Stats** from your _Plugins_ page.

### Git method
1. Just clone the repository in your `/wp-content/plugins/` directory:
```bash
cd ./wp-content/plugins
git clone https://github.com/Pierre-Lannoy/wp-do-not-track-stats.git do-not-track-stats
```

You can now activate **Do Not Track Stats** from your _Plugins_ page.
 
## Contributions

If you find bugs, have good ideas to make this plugin better, you're welcome to submit issues or PRs in this [GitHub repository](https://github.com/Pierre-Lannoy/wp-do-not-track-stats).