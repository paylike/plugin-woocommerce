#Testing

As you can see the plugin is bundled with selenium testing on this repository. You can use the tests, if you have some experience with testing it could be helpful. 
*DO NOT USE IN PRODUCTION, THE TESTS DELETE ORDERS FOR CLEANUP PURPOSES*

## Requirements

* A woocommerce installation is required, in which you need to have the storefront theme installed. 
* You need to create a simple product called "Sunglasses", as well as a simple subscription product called "Subscription". 
* You also need a simple product called "Hoodie"
* You need to have the permalinks enabled, and a user that has already the billing and shipping details filled in (just manually create an order first)
* You need a customer that has "s" in their name to which the system will attribute manual orders

## Getting started

1. Follow 1 and 2 from the [Steward readme page](https://github.com/lmc-eu/steward#getting-started)
2. Create an env file in the root folder and add the following:
`
ENVIRONMENT_URL="https://woocommerce.url"
ENVIRONMENT_USER="username"
ENVIRONMENT_PASS="yourpassword"
`
3. Start the testing server. See
[Steward readme page](https://github.com/lmc-eu/steward#4-run-your-tests)
4. Run  ./vendor/bin/steward run staging chrome --group="woocommerce_test" -vv for the short test
5. Run  ./vendor/bin/steward run staging chrome -vv to go trough all the available tests.

## Problems

Since this is a frontend test, its not always consistent, due to delays or some glitches regarding overlapping elements. If you can't get over an issue please open an issue and I'll take a look. 