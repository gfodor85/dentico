# Dentico

## Google Maps configuration

The map is loaded only when the server provides the `GOOGLE_MAPS_API_KEY`
environment variable. Keep the key out of tracked source files and commit
history. The Maps JavaScript API requires a browser key, so restrict the key in
Google Cloud to the Maps JavaScript API and the exact HTTP referrers used by
the deployed site (and localhost during local development).

For XAMPP on Windows, add a line like the following to Apache's
`conf/httpd.conf`, replacing the value with the newly generated, restricted
key, then restart Apache:

```apache
SetEnv GOOGLE_MAPS_API_KEY "your-restricted-key"
```

For production hosting, set the same environment variable in the hosting
provider's server configuration. If the variable is not set, the page still
loads, but the Google Map script is omitted.
