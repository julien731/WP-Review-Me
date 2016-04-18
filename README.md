# WP Review Me

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/julien731/WP-Review-Me/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/julien731/WP-Review-Me/?branch=master)

Are you distributing WordPress themes or plugins on WordPress.org? Then you know how important reviews are.

The bad thing with reviews is that, while unhappy users love to let the world know, happy users tend to forget reviewing your product.

How can you get more good reviews? Simply ask your users.

![WP Review Me](http://i.imgur.com/iZk4Bgu.png)

## How It Works

Once instantiated, the library will leave an initial timestamp in the user's database.

When the admin is loaded, the current time is compared to the initial timestamp and, when it is time, an admin notice will kindly ask the user to review your product.

The admin notices can, of course, be dismissed by the user. It uses the [WP Dismissible Notices Handler library](https://github.com/julien731/WP-Dismissible-Notices-Handler) for handling notices.

#### Installation

The simplest way to use WP Review Me is to add it as a Composer dependency:

```
composer require julien731/wp-review-me
```

### Example

Creating a new review prompt would look like that:

```
new WRM_WordPress( array( 'days_after' => 10, 'type' => 'plugin', 'slug' => 'my-plugin' ) );
```

This is the simplest way of creating a review prompt. If you want to customize it further, a few advanced parameters are available

## Integrations

Because users sometimes won't take the time to leave a review without an incentive, there are a couple of integrations made just for that.

### Easy Digital Downloads

With the EDD integration, you can automatically generate a discount code for the user in exchange for a review. See the [documentation for the EDD integration](https://github.com/julien731/WP-Review-Me/wiki/Integration:-EDD).