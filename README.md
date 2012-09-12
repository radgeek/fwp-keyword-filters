FWP+: Keyword Filters
=====================
FWP+: Keyword Filters is an add-on module that adds simple filtering abilities
for the FeedWordPress feed aggregator on WordPress weblogs.

Description
-----------

* Author: [Charles Johnson](http://radgeek.com/contact)
* Project URI: <https://github.com/radgeek/fwp-keyword-filters>
* Donate link: <http://feedwordpress.radgeek.com/donate/>
* License: GPL 2. See License below for copyright jots and tittles.
* Requires at least: WordPress 3.0
* Tested up to: WordPress 3.4.2

FWP+: Keyword Filters works together with the FeedWordPress feed aggregator
plugin for WordPress weblogs. With the Keyword Filters module activated, you can
set up filtering rules that determine what to do with incoming posts based on
the keywords found in the text of the post, or on the categories assigned to it
on its original source. Filtering rules can be set up to apply to all of your
subscriptions, or can be applied only to posts coming in from one particular
feed.

Usage
-----

Once you have installed and activated the add-on module (as per Installation
below) go to **Syndication --> Posts & Links** (to set up filtering rules that
apply to posts from *any* of your subscriptions), or go to the **Posts**
settings page for one of the individual feeds you subscribe to (to set up
filtering rules that apply only to posts from *that* particular source). Use the
"+ Add Another Filter" link to set up new rules. The rules in the "Keyword
Filters" section will be applied when a new incoming post matches the patterns
you set up. The rules in the "Default" section will be applied to any posts that
don't match any of the patterns you've set up.

Your rules can be set up to filter posts out (so that they will not appear on
your FeedWordPress site), or to filter posts in (so that they *will* appear on
your site). You can also set up rules to assign matching posts to one or more
particular categories, or to apply one or more tags to them.

By default, FeedWordPress will include any post that appears on a feed, unless a
rule explicitly filters it out. So if you want to ensure that posts only appear
when they match one or more of your filtering rules, set up a "Default" rule
indicating that posts containing no matching keywords will be filtered out. Then
add rules to the "Keyword Filters" section specifying the conditions which will
allow a post to be filtered in.

Rules you set up will either search for posts **placed into certain categories**
on their original source website, or posts **that contain certain keywords**.
You can also search for keywords based on an exact word match, or based on a
regular expression.

**Searching for post categories:** To search for categories on a post, change
the "in their [...]" dropdown so that it reads "in their categories" instead of
"in their text." Then enter the name of the category you want to search for in
the keyword box. Note that **category matches must be exact matches:** putting
`"Stuff"` in the box will match posts that had the category "Stuff" on the
original source website, but not (e.g.) posts that had a category called "Stuff
and Nonsense".

**Searching for keywords in the post text:** To search for one or more keywords
in the text of an incoming post, just type the keywords, one after another, into
the "Posts containing [...]" box. This works roughly like a Google search:
Keyword Filters will search for posts that contain *all* of those keywords, as
complete words (so, for example, "cat dog" will search for posts that contain
*both* the word "cat" by itself, and the word "dog" by itself; it will not match
"categories" or "doggerel"). Keywords match regardless of uppercase or lowercase
letters (so "cat" will match not only "cat," but also "CAT," "cAt," etc.). If
you want to set up filters for posts that contain either "cat" or "dog" but not
necessarily both, set up two separate keyword filtering rules, one for each
term.

**Searching for regular expressions in the post text:** If you need more
flexible searching, you can search incoming posts for a Perl-compatible regular
expression by wrapping the keyword pattern in forward slashes (like /this/). So,
for example, if you need to match anything that includes the letters c-a-t,
including "cat," "cats," "categories," "signification," etc., use the following
pattern:

	/cat/

Regular expression searches are usually case-sensitive, so this will match "cat"
and "categories" but not "Cats" or "signifiCATion." To make the search ignore
case, so that it will match "Cats" or "signifiCATion" as well, use the i
modifier on the pattern:

	/cat/i	

To search for a whole phrase, use a pattern like this one:

	/the fat cat sat on the mat/i

You can use regular expression syntax for more complicated patterns:

	/the \s+ (fat \s+)? cat \s+ sat \s+ (up)?on \s+ the \s+ [a-z]+/ix

Will match "the fat cat sat on the mat", "The CAT sat upon the mat", "The FAT
cAt sat upon the dais," and a number of other phrases.

Installation
------------

To use FWP+: Keyword Filters, you will need:

* an installed and configured copy of [WordPress][] (version 3.0 or later).

* an installed and configured copy of [FeedWordPress][]

* FTP, SFTP or shell access to your web host

Here's what you do.

1.  Download the FWP+: Keyword Filters installation package and extract the
    files on your computer. 

2.  Create a new directory named `fwp-keyword-filters` in the 
    `wp-content/plugins` directory of your WordPress installation. Use an FTP or
    SFTP client to upload the contents of the installation package to the new
    directory that you just created on your web host.

3.  Log in to the WordPress Dashboard, go to the Plugins page, and activate the
    FWP+: Keyword Filters module.

License
-------
FWP+: Keyword Filters plugin is copyright © 2010-2012 by Charles Johnson.

This program is free software; you can redistribute it and/or modify it under
the terms of the [GNU General Public License][] as published by the Free
Software Foundation; either version 2 of the License, or (at your option) any
later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE. See the GNU General Public License for more details.

  [GNU General Public License]: http://www.gnu.org/copyleft/gpl.html

