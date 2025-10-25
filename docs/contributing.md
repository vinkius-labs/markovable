# Contributing

Markovable grows with every pull request, idea, and experiment. Here is how to collaborate with confidence.

## Set Up Locally

```bash
git clone git@github.com:vinkius-labs/markovable.git
cd markovable
composer install
php artisan vendor:publish --provider="VinkiusLabs\\Markovable\\ServiceProvider"
php artisan migrate
```

## Run The Test Suite

```bash
docker compose up -d --build
./vendor/bin/phpunit
```

Add or adjust tests for every behavioral change. Strive for deterministic assertions—the package ships with helpers tailored for that.

## Coding Standards

Markovable follows PSR-12 and adds a few expressive rules:

```bash
composer run check-style
```

Fix issues automatically whenever possible:

```bash
composer run fix-style
```

## Pull Request Checklist

- [ ] Describe the problem and the proposed solution.
- [ ] Include before/after snippets or screenshots when relevant.
- [ ] Cover the change with tests.
- [ ] Ensure workflows pass (`Code Quality`, `Tests`).

## Release Cadence

We favor short-lived branches and frequent releases. When you feel proud of a change, open a PR and ping the maintainers. We love pairing sessions and async feedback alike.

Thanks for shaping Markovable into a sharper companion for Laravel inventors.
