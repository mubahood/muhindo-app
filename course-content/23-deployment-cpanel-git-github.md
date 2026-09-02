# Course 23 ⭐ — cPanel, Git & GitHub: Getting Your System Live

**Tier 2 · Frameworks & Specialisation · Level: Intermediate · Prerequisites: any course that produced a working project · TOP FEATURED**

The step where most self-taught developers stop. You have built something that
runs on your own machine, and it has never been used by anybody, because
nothing you were taught covers buying a domain, uploading a database and
keeping the version that works. This course takes one project from your laptop
to a live address other people can open.

**What you will learn**

- Track your work in Git and recover a version you thought you had lost
- Put a project on GitHub and pull it onto a server without uploading by hand
- Take a PHP and MySQL system live on cPanel, database and all
- Fix a site that worked locally and broke on the server, without guessing

---

## Module 1 — Git, for one person who does not want to lose work

1. **What Git is and the problem it removes** — the folder named final-final-v3-USE-THIS, and why it is not a version history.
2. **Install Git and set your name once** — the two config lines every commit depends on.
3. **Make your first repository** — init, status, and understanding what is and is not being tracked.
4. **Stage and commit** — add, commit, and what a commit actually is.
5. **Write a commit message somebody can use** — including you, in four months, looking for the change that broke something.
6. **Read your own history** — log, diff, show, and how to see exactly what changed in one commit.
7. **Undo things safely** — restore an edited file, unstage, and amend the message you got wrong.
8. **Ignore what should never be committed** — .env, vendor, node_modules, uploads, and why a committed .env is a security incident.

```
# .gitignore for a PHP project
/vendor
/node_modules
.env
/storage/*.key
public/uploads/
```

9. **Branch to try something risky** — make a branch, break things, come back to a working main.

## Module 2 — GitHub, and working from anywhere

10. **Create a repository and push to it** — remote, push, and what "origin" means.
11. **Public or private, and what that decides** — who can read your code, and what a public repo tells an employer.
12. **Clone onto another machine** — the same project on a laptop and a desktop, in sync.
13. **Pull, and resolve your first conflict** — the moment two versions disagree, done slowly.
14. **Write a README that explains the project** — what it is, how to run it, what it needs. The first thing anybody sees.
15. **Use GitHub as your portfolio** — pinned repositories, a clean profile, and what a hiring manager actually looks at.
16. **Ask AI to explain an unfamiliar repository** — point an assistant at code you did not write and have it summarise the structure, then verify by opening two files it described. Useful and untrustworthy in equal measure.

## Module 3 — cPanel: the server side

17. **What web hosting actually is** — a domain, a server, DNS, and how a typed address finds your files.
18. **Buy a domain and hosting** — what to look for, what the cheap plans leave out, and roughly what it costs in UGX.
19. **Find your way around cPanel** — File Manager, phpMyAdmin, subdomains, and the parts you never need.
20. **Upload a site and see it live** — the first time your work has a real address. ▶ https://www.youtube.com/watch?v=xUB8gQfPIz0
21. **public_html, document roots and subdomains** — where files must sit for a URL to reach them, and why a Laravel project is the awkward case.
22. **Create a database and a user** — in phpMyAdmin, with the privileges that are actually needed.
23. **Export from local and import to live** — moving your MySQL data across without losing it.
24. **Point the app at the live database** — connection settings, and the three that are always wrong first.

## Module 4 — Deploying from GitHub, properly

25. **Deploy with GitHub instead of dragging files** — pulling your repository onto the server, so the live site is a version you can name. ▶ https://www.youtube.com/watch?v=fCobgy9w_R0
26. **Set up SSH keys** — deploying without typing a password every time.
27. **Deploy an update without breaking the live site** — the order of operations: pull, dependencies, migrations, clear caches.
28. **Roll back a bad deploy** — how to get the previous version live again in under two minutes, which is the only reason any of this is worth doing.

## Module 5 — When it worked locally and not on the server

29. **Read the error log first** — where cPanel keeps it, and why the white screen is never the information.
30. **Turn on errors safely, then turn them off** — seeing the real message without showing it to the public.
31. **Fix file permissions** — the storage and cache folders that must be writable, and the numbers that mean what.
32. **Fix broken paths and mixed content** — assets that load locally and 404 live, http assets on an https page.
33. **Install a free SSL certificate** — the padlock, and why a form without one is a liability.
34. **Debug a deployment with AI, and check what it tells you** — paste the real error, get three suggested causes, and test each against the log rather than applying all three at once. An assistant that has never seen your server is guessing; the log is not.

## Final project

Take a project you have already built in another course and **put it live**: in
Git with a readable history, pushed to GitHub with a real README, deployed to a
domain over SSL with its database imported, and reachable by anybody. Then make
one small change locally, deploy it the proper way, and roll it back. You
submit the live URL, the repository link, and a short note on what broke on the
server that had never broken on your machine.

**Quiz ideas:** which command for which situation · what belongs in .gitignore,
choose from a list · given an error log extract, name the cause · order the four
steps of a safe deploy · true or false on committing a .env file.

**Continue to:** Course 10 (Laravel Essentials) or Course 19 (Build a Complete
Online Shop) if you want a bigger system to deploy next.

## Decisions

- Git is taught before GitHub and both before hosting, because the value of
  deploying from a repository cannot be explained to somebody who has not yet
  lost work to a folder full of copies.
- Two existing videos are reused, both verified live in the link report:
  xUB8gQfPIz0 (cPanel hosting, from course 16) and fCobgy9w_R0 (deploy from
  GitHub to cPanel, from course 10). Everything else needs recording.
- Rollback gets its own lesson. Every deployment tutorial teaches getting code
  up and almost none teaches getting it back, which is the only thing that makes
  deploying on a Friday survivable.
- Level is intermediate and the prerequisite is deliberately loose: any course
  that produced a working project qualifies, because this one is about the step
  after building, whatever was built.
