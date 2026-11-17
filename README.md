# Business Directory

A PHP/MySQL directory for sites running Event Forge 0.7.2. Copy `business/`
into the existing Event Forge `events/` directory and update the shared panel
at the same time. Sign in as an Event Forge administrator and select **Set up
available modules** on Overview to install or upgrade the directory. Existing
listing IDs and values survive the additive upgrade. The database account
needs CREATE and ALTER privileges for setup.

## Listing fields

The editor supports name, category, subcategory, description, street address,
city, state, postal code, phone, owner/contact name, email, website,
sponsorship level, membership, coordinates, QR destination URL, photo, logo,
and audio. Legacy status and sort values can also be stored. Publication
is an explicit separate setting. Numeric legacy status meanings are not
inferred during an upgrade.

The public feed at `GET /events/business/api.php` returns
`{"businesses":[...]}` containing published listings. Existing fields remain
available. Expanded public fields include address components, email,
category/subcategory, membership/sponsorship, coordinates, QR destination and
media filenames, plus `photo_url`, `logo_url` and `audio_url`. Owner/contact
name, raw workflow status, and internal sort values are private to the panel.
Render listing text as text in frontend consumers.

## Media

Upload photos, logos and audio from the editor, or enter an existing filename.
Supported images are JPEG, PNG, WebP and GIF. Supported audio is MP3, WAV and
OGG. Files are limited to 10 MB and checked by MIME type. PHP needs Fileinfo,
and `upload_max_filesize` and `post_max_size` must accommodate the uploads.
New uploads receive random filenames. Replacing media retains the old file
because another listing may reference it.

Media lives in `business/storage/uploads/` and is served through
`business/media.php?file=FILENAME`. Copy the directory's `.htaccess` file as
well, which denies direct requests on Apache. On nginx, deny direct access
to `/event-forge/events/business/storage/` using the actual installation
prefix. The PHP media endpoint reads and serves the allowed file types.
Grant PHP write access to the upload directory. Preserve that directory
when uploading future code updates.

## Legacy Directus import

Restore the supplied Directus dump into a separate temporary database. Its
legacy `events` table conflicts with Event Forge. Use a private PHP file
outside the web root returning the source connection array with `host`,
`port`, `name`, `user`, `pass`, and `charset` keys.

Run from the installed `events/` directory, supplying the legacy status code
that should be published as the final argument. This is an explicit migration
choice that must be confirmed from the existing client behavior.

```sh
php business/import-legacy.php /private/legacy-db.php PUBLIC_STATUS_CODE
```

The import reads only `members` and their `directus_files` references,
preserves member IDs, maps media IDs to filenames, and stores the expanded
records in `business_directory_listings`. A missing media record or ID
collision rolls back the entire import. The script does not overwrite
existing listings or download files. Copy the old files from
`franklin-county.com/directus/storage/uploads` into the new upload directory.

This feed is the directory's native API. Exact kiosk and mobile application
response adapters still require the existing client endpoint contract.

## Website integration

The public website reads this module through the API. The panel creates and
edits source records. `GET business/api.php?id=123` returns
`{"business":{...}}` for a published listing. Draft or missing IDs return
404, and invalid IDs return 400. A list request keeps the existing
`{"businesses":[...]}` wrapper. Both responses exclude internal fields and
include the resolved media URLs. The itinerary reference frontend uses this
same listing API alongside Event Forge's event feed.
