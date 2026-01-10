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

Page inclusion
--------------

Pages shown in the checklist are controlled by the extension setting
``includeDoktypes`` (comma-separated). When empty, all doktypes are included.
When set, only those doktypes are included unless overridden per page.

Two page properties are available on default-language pages:

* **Exclude from GO Live checklist**: force a page out of the checklist.
* **Include in GO Live checklist**: force a page into the checklist.

The include flag wins over exclude. If neither is set, the doktype setting
applies.

.. tip::

   Set ``includeDoktypes`` to ``-1`` to exclude all doktypes by default, then
   manually include specific pages.

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
