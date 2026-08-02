# Course 18 ⭐ — HotelPro: Hotel Booking Management System (PHP & MySQL)

**Tier 3 · Capstone Systems · Level: Intermediate · Prerequisites: Courses 01, 03, 04, 05 · TOP FEATURED**

The perfect first capstone: a complete hotel booking system in pure PHP & MySQL — no
framework hiding the logic. You build both sides: the customer site (browse rooms,
book, dashboard) and the admin office (rooms, categories, bookings, review flow).
After this, frameworks feel like a luxury, not magic.

**System features:** room categories & rooms, image galleries, landing page,
authentication, customer booking flow, customer & admin dashboards, order syncing.

▶ Full playlist: https://www.youtube.com/playlist?list=PLOR5hj0X3WPc19d5SPFxinGtzUfpJar6I

---

## Phase A — Foundations & admin

1. Introduction & project setup — https://www.youtube.com/watch?v=oUmIaVaoumY
2. Dynamic input fields — https://www.youtube.com/watch?v=-jY96m7XDbo
3. User authentication & security — https://www.youtube.com/watch?v=_WZAYfLLmFQ
4. Managing room bookings — https://www.youtube.com/watch?v=Eu4CwgPZN-0
5. Managing hotel categories — https://www.youtube.com/watch?v=aC_x2THI25M
6. Image uploading — https://www.youtube.com/watch?v=wHwhMDJlgj4
7. Edit room-category form — https://www.youtube.com/watch?v=tsM7iLyDd-Q
8. Structuring room data — https://www.youtube.com/watch?v=TdsheEEPjNA
9. Room form uploading — https://www.youtube.com/watch?v=i2bB7U6cBcc
10. Managing room gallery photos — https://www.youtube.com/watch?v=MSDrRV9FTbc

## Phase B — The customer experience

11. Building the landing page — https://www.youtube.com/watch?v=kF6KlGqbRYg
12. Dummy content setup — https://www.youtube.com/watch?v=SBgCm5IYKw8
13. Room detail screen — https://www.youtube.com/watch?v=8rGxMV1WP2s
14. Room order screen — https://www.youtube.com/watch?v=B_DHUNXBREI
15. Room order data structure — https://www.youtube.com/watch?v=59erkpnbHZI
16. Customer room booking — https://www.youtube.com/watch?v=fW_6aXvlnQg
17. Customer dashboard — https://www.youtube.com/watch?v=g_6CYyuRMrw
18. Login logic complete — https://www.youtube.com/watch?v=SBCgvCjHv8E

## Phase C — Admin operations

19. Admin booking management — https://www.youtube.com/watch?v=aiwK77ZyXog
20. Admin booking review — https://www.youtube.com/watch?v=SXwuC1m0Nsg
21. Order syncing — https://www.youtube.com/watch?v=GEmvxhk_dZY

```php
// The booking check at the heart of the system:
$stmt = $pdo->prepare(
  "SELECT COUNT(*) FROM bookings
   WHERE room_id = ? AND status != 'cancelled'
   AND check_in < ? AND check_out > ?"
);
$stmt->execute([$roomId, $checkOut, $checkIn]);
$available = $stmt->fetchColumn() == 0;   // no overlap = bookable
```

## Graduation assignment

Adapt HotelPro into a **guesthouse or apartments system** for a real business in
your town: their categories, real photos, mobile-friendly landing page, plus one
feature the videos don't build (e.g. booking-confirmation email, or an
availability calendar). Harden it with Course 08 security checks before submitting.

**Milestone quizzes:** schema quiz after Phase A · booking-overlap logic exercise
(the query above, with edge cases) · security checklist audit at the end.
