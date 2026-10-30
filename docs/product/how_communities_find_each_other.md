# How communities find each other

For the moment in a demo when someone asks "so how would anyone find this?" or
"is this like Mastodon?" Written 2026-10-30. The reasoning behind it is in
[the discovery essays](instance_discovery_for_and_against.md); the rule itself
is in [PRINCIPLES.md](../../PRINCIPLES.md#communities-describe-themselves-instances-look-outward-only-where-their-operator-points-them).

## The short answer

A v3 site is a website. People find it the way they find any website: a link, a
search engine, a friend. v3 does not run a directory of communities and does not
go looking for other sites on its own.

What v3 is designed to do is make sure a community can never get lost. The
community writes down, inside its own record, where its copies and its
successors live. If this site goes away, that note goes with every backup, so
the people who have one know where to go next. That record is decided and not
yet built; today the Backup download carries everything except that note.

## "Is this like Mastodon or Nostr?"

No, and the difference is worth thirty seconds.

Mastodon servers talk to each other all the time. That is how your post reaches
someone on another server. It also means your server decides which other servers
you are allowed to see, and most people end up on a few big ones.

Nostr goes the other way: you post to several "relays" and readers pull from
them. The clever part is that you publish a signed list of where you post, and
readers follow that list. The messy part is everything that tried to turn that
into a global feed.

v3 keeps the clever part and skips the rest. Each community is its own site. It
will publish a signed note saying where its copies are, and a member will be
able to publish a signed note saying where else they post. Nothing in v3 talks
to another site unless the person running it typed in that site's address.

## "So two communities can never connect?"

They can, when their operators choose to.

The site operator can point their v3 at another v3 and pull in its public
posts. That exists today and works like syncing an archive. A club with three
sister clubs could use it to show what the others are up to. It is off unless
the operator turns it on, and it only reaches the sites the operator named.

What v3 will not do is ship with a built-in list of "recommended" sites, crawl
the internet for other v3 sites, or rank other communities. The first site
that becomes everyone's default would quietly become the network, and that is
the thing v3 is built not to be.

## "What if the person running this disappears?"

Two things are true today and one is still to build.

1. Anyone with read access can download the whole current record from the
   Backup page and rebuild a working site from it.
2. The community's own record will name its mirrors and successors, so members
   know where the new home is. This is the part still to build.
3. The posts are signed by their authors, so anyone can check that each post
   on the new site is the one its author wrote.

That is the whole design: the community, not the server, is the thing that
persists.

## "Can I follow one person across several of these?"

Not yet. The design is that a member states in their own profile where else
their key posts, signed like everything else, and readers follow that. It sits
behind notifications, search and key recovery, which matter more to the people
already here.

## The one-line version

v3 sites describe themselves and never go looking for each other. A community
says where its copies are; a person says where else they post; the operator
decides which other sites, if any, theirs listens to. The web does the rest.
