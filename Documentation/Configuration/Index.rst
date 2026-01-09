.. include:: ../Includes.txt

Configuration
=============

Checklist items are stored as records in :php:`tx_hdgolive_checkitem`.

Item scoping
------------

Checklist items are resolved by PID:

* PID 0: global items used for all sites
* PID = site root page ID: items for that site only

If both exist, items are combined. Items with the same key are overridden by
the later entry (site-specific wins).

Item fields
-----------

* Title
* Key (unique identifier used by item checks)
* Description (shown on the item detail page)

Where to create items
---------------------

Create records in the backend list module:

* Use PID 0 for global items.
* Use the site root page for site-specific items.
