# Sanfaani Schools

Sanfaani Schools is a result checker and school management SaaS product.

## MVP Focus

The first version focuses on:

- School registration
- School admin dashboard
- Student management
- Result upload
- Scratch card generation
- Public result checker
- Result printing and PDF download

## Branch Workflow

- `main` = production
- `staging` = pre-production testing
- `dev` = active development
- `feature/*` = individual feature branches

# Developer Workflow

## Step 1: Clone the repository

```bash
git clone https://github.com/sanfaani-labs/sanfaani-schools.git
cd sanfaani-schools

Step 2: Switch to dev
git checkout dev
git pull origin dev
Step 3: Create feature branch
git checkout -b feature/result-checker
Step 4: Work and commit
git add .
git commit -m "feat: add result checker form"
Step 5: Push branch
git push origin feature/result-checker
Step 6: Open pull request

Open a PR from:

feature/result-checker → dev
Step 7: Review and merge

Another team member reviews before merge.


---

# 18. Recommended merge flow

Use this:

```text
feature branch → dev → staging → main

## Rule

No direct work on `main`.
All work must go through pull requests.
