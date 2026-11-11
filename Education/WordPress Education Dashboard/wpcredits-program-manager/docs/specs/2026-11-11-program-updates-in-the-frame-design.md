# Program updates in the frame, and no Resources on the Reference view (design, 11 November 2026)

Plugin: WPCredits Program Manager 1.123.71, with WPCredits theme 1.32.24. Follows the Administrator guide in
the frame (`2026-11-11-administrator-guide-frame-design.md`, 1.123.70).

## 1. The product owner's asks (11 November 2026, on the live Reference view)

1. **Program updates:** "Move to sidebar, posts should open like the Administrator guide, on the right
   sidebar should be a list of other/previous posts."
2. **Resources:** "Not needed, duplicated." Its Slack, handbook and Need help? are the menu's Help, its way
   into wp-admin the menu's own.
3. **"What is Reference? What does it mean? Should it be called that way?"** The view's name was the
   builder's (1.123.69) and said nothing of what it holds. The product owner chose **The program** of three
   offered (The program, At a glance, keep Reference): the menu item and the view's serif title. The
   address keeps `?wpcpm_view=reference`.
4. **"What's the difference between Watch and Overview? Shouldn't be they separate sections with different
   URLs?"** Watch was a column of the Overview that the menu jumped to. Asked whether to hold this release
   for it, the product owner chose to. Watch is its own view at `?wpcpm_view=watch`: the page's name, "Watch"
   in the serif, a lede, then the group "This week" (not "Watch" twice), every card folded. The Overview
   keeps the head, the figures and Waiting for you, one column. `card_url( $card )` and `view_of( $card )`
   send every link to a card (mails, wp-admin screens, the assistant's answers, a decision's way back) to the
   view that draws it, which also mends links to The program's cards, sent to the Overview since 1.123.69.
   The main area packs its rows to the top, so a short view is not stretched to the menu's height.

## 2. What is built

- **The menu:** Program updates after The program, linking the newest update the Administrator Dashboard
  lists (`WPCPM_Updates::posts( 1, 'administrator' )`: the Updates category, public or at the
  Administrators level, published or private, that the reader may open), only while there is one.
- **An update:** for an Administrator, in a theme that names `administrator-updates`, the post is drawn in
  the frame (`WPCPM_Administrator_Updates_View`): the dashboard's menu with Program updates marked, the
  post's own title (not core's "Private:" one) as the one H1 in the serif, its date, the post, and in the
  guide's right-hand column the Administrator Dashboard's updates, newest first, up to 20, each with its
  date, the one being read marked as the menu marks the current page. One update alone has no list. The
  layout, sheet and script are the guide's; on a narrow main area the list folds under the title.
- **Who:** the theme asks `WPCPM_Administrator_Updates_View::draws( $post )`, post by post and reader by
  reader, because a public update is read by everybody and only an Administrator gets the frame;
  `WPCPM_Updates::lists( $post, 'administrator' )` is the same test `posts()` puts each row to.
- **The program view** (was Reference) ends with Tools from our sponsors, under a screen reader's h2 "From our sponsors".

**Builder's calls:** the item's place after Reference and its inbox icon; the list's cap of 20; no list for
a single update.

## 3. Not in this release

A count of unread updates beside the menu item; the other dashboards' updates columns.
