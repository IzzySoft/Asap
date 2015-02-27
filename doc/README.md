# Amazons Product Advertising API for PHP

This document will describe some basics about the API. A detailed API reference
for the classes shipping along can be found in the `doc/apiref` subdirectory.


## PHP Files

* `amazon_aws_signed_request.php`:  
  This is the "foundation", responsible to send our requests to AWS in the
  required format. You won't have to deal with this directly, so it will not be
  detailed. Credits to Ulrich Mierendorff (original work) and Sameer Borate
  (CURL integration). I just did some formatting and ApiDoc integration here.
  Let's call that *The Code", for reference.
* `amazon_api_class.php`:  
  That's where the major functionality is in. Together with the `Example.php`,
  the `unitTest/*`, and *The Code*, this is what I've started my work with.
  You can work with this class directly, and then only need these two files –
  which, for reference, I will call here *The CodeDiesel*, as that's where
  I've picked them from (see above links). I won't go into detail on this
  file either; refer to above links instead.  
  Credits go to Sameer Borate here again. I only applied a few minor changes
  indicated on the mentioned *Wern-Ancheta* Blog article (so again, not my
  own work) – and some minor improvements as pointed out by corresponding
  `@log` entries in the ApiDoc.
* `class.simplecache.php` and `amazon_simple_ads_class.php`:  
  Now, *that's* my work. To have some fun, I'll refer to this layer as
  *The CodeDieselIzzy*: it doesn't work without the other parts, but builds
  upon it. Most major features it adds include:
  - the possibility to use multiple SearchIndexes with one call
  - the possibility to use "keyword groups" with a single call

  So in short words, with *The CodeDieselIzzy* you could place a single call
  to initiate a search for "Code Diesel" and "Code Izzy" on the search
  indexes "Electronics" and "Books". Well, that's certainly a thing the
  original Amazon API says doesn't work; so we trick it by running multiple
  searches and merge the results – don't overdo it, or you'll violate the
  TOS soon with more than 40 requests per second ;) To help you prevent that,
  the cache class will cache returned search results for 24h (exactly as
  defined by the TOS). I.e., when you issue the very same search a second
  time, it's just the cache being queried.


## Installation

Simply put all the `*.php` files into the very same directory. You might wish
to chose one that's in your PHP include path, for easier access. If you're
planning to only use *The CodeDiesel* API, that's all to be done – and you
need to continue reading at the places pointed out above.

If you want to use *The CodeDieselIzzy*, it's recommended to adjust the
cache path in `amazon_api_class.php` to point to a place where your web server
has read/write access to. That's where search results returned from AWS are
temporarily stored for 24h. If the same search is issued after that, the
cache file is replaced. If not, the file will stay forever; so if you're
generating your queries dynamically, you might wish to set up a Cron job to
take care for older files.


## Usage

This part will just detail *The CodeDieselIzzy*. For *The CodeDiesel*, please
refer to above links.

### Class initialization
Having retrieved your credentials from AWS, you somehow need to tell the class
to use those. In order to not having to do this on each call, they are stored
into class variables on initialization. So in your PHP code, you need to start
with something similar to the following lines, while replacing the fake-credentials
with your real ones:

```
require_once('amazon_simple_ads_class.php');
$amazon = new AmazonAds('public_key', 'private_key', 'associate_id', 'com');
```

As written, use your AWS public and private keys here, plus the Amazon
PartnerNet ID (looks like 'foobar-21' (Europe) or 'foobar-20' (US).
Parameter #4 is optional and defines the site you intend to link to.

### Querying data
Here we have mainly two approaches:

* we know the ASIN of a product (or multiple) we want to get details on
* we just want to search for some products matching certain criteria

The first is easy: we know exactly how many records to expect, and rawly
what data they might contain. We know the product type, etc.pp. So no much
fuzz – this is done and explained easily:

```
// a comma-separated list is allowed; and yes, these are books (ASIN=ISBN-10)
$asin = '3645603115,3645602151';
$res = $amazon->getItemByAsin($asin);
```

The second is a little more tricky. We need to know e.g.

* search terms to look for
* where to look for them (the complete catalog often is too much)

So let's take a simple example first, and search for 5 books on PHP programming:

```
$keywords = 'PHP Programming';
$catalog  = 'Books';
$limit    = 5;
$res2 = $amazon->getItemsByKeyword($keywords,$prodgroup,$limit);
```

This will give us max 5 books on PHP programming (hopefully; and the limit
cannot exceed 10). Still easy, and still in the bounds of what the official
API describes (and you could do with *The CodeDiesel* as well, except for the
caching part). So let's go a step further, and look for books on either
PHP or Ruby programming, with a single call:

```
$keywords = 'PHP Ruby +Programming';
$catalog  = 'Books';
$limit    = 5;
$res2 = $amazon->getItemsByKeyword($keywords,$prodgroup,$limit);
```

Note the leading "+" sign for Programming? That means as much as "do multiple
searches *all with this keyword*, combining it with each of the others. *The
CodeDieselIzzy* then takes care to

* run multiple searches, here:
  - one for "PHP Programming"
  - one for "Ruby Programming"
  - both on the "Books" index
* merge the results, sort out duplicates
* return random 5 articles from the results

Pretty neat, isn't it? Now, what happens if we search for 'PHP Ruby +Advanced +Programming'?
Much the same, but again only 2 searches: "PHP Advanced Programming" and
"Ruby Advanced Programming". So multiple words prefixed by a "+" will "stick
together".

Not enough? Yeah, what about Video Tutorials?

```
$keywords = 'PHP Ruby +Programming';
$catalog  = 'Books,DVD';
$limit    = 5;
$res2 = $amazon->getItemsByKeyword($keywords,$prodgroup,$limit);
```

Now, *that's* something, right? It would do the same as the previous example,
then repeat the same on the "DVD" index, merge everything, remove dupes, return
5 items (note that dupe-check and limiting the result set will always be the
last steps only, so we have a maximum pool to chose from).


### Evaluating results
I have to admit, I somehow minimized things a little. We're talking about
placing some little ads on our site – not about presenting a full-fledged
catalog. So there aren't that many details returned by *The CodeDieselIzzy*
API. It's an array[0..$limit] of arrays with the keys:

* title: title of the item, obviously
* price: formatted, with currency, e.g. "EUR 20,00"
* img:   URL to the small (75px high) product image
* url:   where to send our visitors to (includes our partner tag and all)

I simply tried to stick to fields available to all Amazon products, and have the
basic data required for ads available. If there's demand, the ASIN could be
easily added – and then be used to query the full set of data via *The CodeDiesel*.
