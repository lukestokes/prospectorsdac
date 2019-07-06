# prospectorsdac
A little playground to play around with prospectors.io data as if a plot were run as a DAC

Things I'd still like to add or improve on:

* Include the health of items transferred into and out of storage as part of a value calculation.
* Factor in the time required to craft items and the value of those items transferred.
* Keep track of how the community-crafted tools were used such as if the resources obtained with them were put back in storage or if the worker got paid (and how much) by the DAC for completing a job order.
* Using the EOS, PGL, and USD valuations at the time of a transaction, keep track of capital investment per user as well as time investment.
* Eventually create a DAC for those involved, contributing tokens based on involvement. The token holders will then vote on what the DAC does with the pool of resources (reimburse investors, build a building, expand to include more plots, etc).
* Expand these tools for others unions/DACs within Prospectors (and/or consider joining an existing group)

To run this, you'll need an `.api_credentials.json` file with the following format for use with your <a href="https://www.dfuse.io/en">DFuse account</a>:

```
{"api_key":"server_....","token":"eyJhbGciOiJLT.....", "expires_at": 1562444037}
```

It will write json files to the cache folder, so make sure the account running your webserver has permissions to do so.

You can find me in Prospectors.io as 1lukestokes1 at 12/-19.

Donations are certainly appreciated if you find this code useful.

Add this to your nginx config to block access to the credentials file:

```
location ~ /\.  { deny all; return 404; }
```