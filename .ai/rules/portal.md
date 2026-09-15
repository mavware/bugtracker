---
paths:
  - 'resources/views/pages/portal/**'
---

# Portal

## The client portal is keyed on customers.client_user_id, not on a role
A professional's customer record gains portal access through an invitation link (App\Actions\Portal\CustomerPortalAccess: invite/revoke/unlink/findByToken/accept; token stored plain on customers.portal_invite_token, INVITATION_DAYS = 7, spent on accept). Accepting at portal.invitations.show links customers.client_user_id to the signed-in account; the route carries auth so a stranger goes through login/register and Fortify's intended() brings them back. There is no Client role and no Permission for the portal on purpose: access is a matter of record, like the customer pickers, so User::isPortalClient() (any linked property) drives the sidebar group and the pages authorise by construction — pages::portal.index only finds sessions whose customer_id is in Auth::user()->clientProperties(), and pages::portal.rooms uses RoomLabels::groupsForClient (same keys as the owner's page, so both rewrite the same sessions). A client edits only a night's name and room; SurveillanceSessionPolicy stays owner-only, so reports and images are not opened to clients — add a deliberate client ability if that is ever wanted, never a Gate::before. client_user_id and the token are never fillable; they change only through CustomerPortalAccess. The professional manages all of it from the Portal column of pages::dashboard.customers.

## Portal rooms come from ManageRooms::linkedTo, and a client's room edit files under the professional's property
CORRECTION: pages::portal.rooms uses ManageRooms::linkedTo(Auth::user()) (rooms whose customer_id is one of the client's linked properties) instead of RoomLabels::groupsForClient; these are the same Room rows the professional's pages::dashboard.rooms lists, so a rename on either side is the same rename. pages::portal.index saves a night's room with $session->moveToRoomNamed(), which resolves the typed name under the session's own user_id/customer_id (the professional's account and property), never under the client's account, so the client never gets rooms of their own.
