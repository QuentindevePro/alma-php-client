CHANGELOG
=========

v0.0.2
------

* Updates root namespace to `Alma\API` instead of just `Alma`
* Adds PSR-4 autoload config to `composer.json`

v0.0.1
------

* Initial "pre-release" of the API Client
* Includes two main endpoints: Payments and Merchants
* Provides what's necessary for a typical payment flow:
    * `Payments.createPayment` and `Payments.eligibility`
    * `Merchants.me`
* Base `Alma\API\Client` class can be configured with API key and live/test mode
* TLS is automatically forced to TLS 1.2 to meet Alma's security requirements, but configurable