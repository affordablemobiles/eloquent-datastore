## Examples

### Auto Generated `int` Primary Key

* [`SELECT` style queries](Http/Controllers/QueryController.php)
     * [Associated model](Models/People.php)
* [DML queries](Http/Controllers/DMLQueryController.php) (`INSERT` / `UPDATE` / `DELETE`)
     * [Associated model](Models/People.php)
* [Ancestor Queries & Relationships](Http/Controllers/AncestorQueryController.php)
     * [Associated model](Models/People.php)
* [Setting a Namespace](Models/Order.php) (see `__construct()`)
* [JSON Cast example](Http/Controllers/OrderJSONController.php)
     * [Associated model](Models/Order.php)
* Real world-ish [Basket example](Http/Controllers/BasketController.php)
     * [Associated model](Models/Basket.php)
* Example of model caching within a request: [Cached Basket](Http/Controllers/BasketCachedController.php)
     * [Associated model](Models/BasketCached.php)

### User Generated `string` Primary Key

* [`SELECT` style queries](Http/Controllers/Named/QueryController.php)
     * [Associated model](Models/Named/People.php)
* [DML queries](Http/Controllers/Named/DMLQueryController.php) (`INSERT` / `UPDATE` / `DELETE`)
     * [Associated model](Models/Named/People.php)
* [JSON Cast example](Http/Controllers/Named/OrderJSONController.php)
     * [Associated model](Models/Named/Order.php)
* Real world-ish [Basket example](Http/Controllers/Named/BasketController.php)
     * [Associated model](Models/Named/Basket.php)