.. include:: ../Includes.txt

Installation
============

1. Install the extension as usual (Composer or Extension Manager).
   Composer example: :shell:`composer require hyperdigital/hd-golive`
2. Flush caches in the backend.
3. Run the database schema update to add required tables and fields.

Database schema
---------------

The extension adds tables:

* tx_hdgolive_session
* tx_hdgolive_checkitem
* tx_hdgolive_itemcheck
* tx_hdgolive_pagecheck
* tx_hdgolive_note
* tx_hdgolive_pagenote
