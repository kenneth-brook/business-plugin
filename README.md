# Business Directory

A PHP/MySQL directory for sites running Event Forge 0.7.2. Copy the repository's
`business/` directory into the existing Event Forge `events/` directory.
The module uses Event Forge's database configuration and owns the additive
`business_directory_listings` table. It operates without the itinerary module.

Install the schema and manage listings from [the shared panel](https://github.com/kenneth-brook/panel-plugin).
Listings support a name, category, description, address, phone and website.
New listings are drafts. Unpublish a listing to remove it from the public API
while retaining its ID for existing itinerary references.

`GET /events/business/api.php` returns `{"businesses":[...]}`, sorted by name
and ID. Each entry includes `id`, `name`, `category`, `description`,
`address`, `phone` and `website`. Drafts are excluded. Use the actual Event
Forge URL prefix if installed under a subdirectory. Fetch from the same
origin in the browser or from the website's backend if it is on another
origin. Render listing text as text and use the website field as a link.

For a panel-free deployment with PHP CLI access, run from the installed
`events/` directory:

```sh
php -r 'require "includes/installer.php"; require "business/store.php"; $db = eventforge_bootstrap_connection(); if (!$db) { exit(1); } business_install($db);'
```
