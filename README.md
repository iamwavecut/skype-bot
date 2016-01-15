# Skype web-api based bot 
This is my pet-project and it may contain ugly and scary pieces of code, but I'm open to pull-requests and improvements, because its a lot of work to do to make it production ready.

It's made as headless daemon, based on `React/event-loop` and `Sabre/event`, using `guzzle` and, for now, some raw `curl`. Also, I tried to make it DB-independent so it stores all data in.json files. You can check `composer.json` to investigate extra dependencies.

## Roadmap

1. Refactoring:
  - [ ] Core: use guzzle promises instead of raw curl
  - [ ] Internationalization
  - [ ] Separate plugins to other repos
  - [ ] Refactor plugin manager (loading order, recursive dep's checks, etc)
  - [ ] Refactor DI: change annotations injections to constructor, to see next
  - [ ] Cover all with tests (f.e. phpunit)
  - [x] to be updated


## Installation
To be updated. **`php5.6+`**
