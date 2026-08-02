# Course 20 ⭐ — Android E-Commerce App with Firebase

**Tier 3 · Capstone Systems · Level: Intermediate–Advanced · Prerequisites: Course 14 · TOP FEATURED**

Build a complete shopping app with **no backend server at all** — Firebase provides
the database (Firestore), auth and hosting-free sync. This capstone also takes you
through the finish line most tutorials skip: publishing on the Google Play Store.

**System features:** product catalogue on Firestore, live data sync, cart, checkout
flow, and a real Play Store release.

---

## Phase A — Firebase foundations

1. **Firestore introduction: your database in the cloud** —
   ▶ https://www.youtube.com/watch?v=i2M1Fu6VgjY
2. **Project build, part 2.1** — ▶ https://www.youtube.com/watch?v=kW8Pi6ec2mo
3. **Project build, part 3** — ▶ https://www.youtube.com/watch?v=MgHalJfvPeY

> **How Firestore thinks:** no tables — *collections* of *documents*. A product is a
> document in the `products` collection; the app listens and updates live.

```java
FirebaseFirestore.getInstance()
    .collection("products")
    .addSnapshotListener((snap, e) -> {
        products.clear();
        for (DocumentSnapshot d : snap) products.add(d.toObject(Product.class));
        adapter.notifyDataSetChanged();   // UI updates the moment data changes
    });
```

## Phase B — Building the shop

4. **Project build, part 4** — ▶ https://www.youtube.com/watch?v=mGw2HL4qbMU
5. **Project build, part 5** — ▶ https://www.youtube.com/watch?v=EUQgcmvd1IU
6. **Firestore data operations deep-dive** — ▶ https://www.youtube.com/watch?v=8p_-QSbhSAU
7. **Project build, part 7** — ▶ https://www.youtube.com/watch?v=n030ZdUqy5k

## Phase C — Going public

8. **Upload your app to the Play Store** — signing, listing, release.
   ▶ https://www.youtube.com/watch?v=5tpNTceT5mM

## Extension briefs (guided, no video needed)

9. **Firebase Authentication** — email/password sign-in; guard the checkout.
10. **Security rules** — only owners edit their cart; read-only products.
11. **Push notifications (FCM)** — "your order has shipped" messages.

## Graduation assignment

Publish a real mini-shop app (even to Play Store *internal testing*): 10+ products
in Firestore, live updates proven (edit a price in the console, watch the app), cart
+ order saved per signed-in user, and one extension from 9–11. Submit the console
screenshot + APK/testing link.

**Milestone quizzes:** SQL table → Firestore collection mapping quiz · snapshot
listener prediction question · release-checklist ordering (keystore → bundle →
listing → review).
