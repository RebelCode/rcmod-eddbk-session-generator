# Change log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [[*next-version*]] - YYYY-MM-DD

## [0.1-alpha9] - 2019-01-16
### Fixed
- Sessions were not being generated when the timezone was a UTC offset.

## [0.1-alpha8] - 2018-12-05
### Added
- Sessions may be generated for combinations of services and resources.

### Changed
- Now using the new collective `rebelcode/booking-system` package.
- Replace availability rule iterators with availability classes.
- Now using the new service session types instead of the session lengths for generation.

## [0.1-alpha7] - 2018-08-27
### Fixed
- A changed monthly repeating mode name was incorrect - `date_of_month` instead of `day_of_month`.

## [0.1-alpha6] - 2018-08-24
### Changed
- Monthly repeating mode constants were renamed.

### Fixed
- Rules where not generating sessions for the last repeating unit.
- Fixed weekly repetition not generating any sessions when the rule is longer than a day.

## [0.1-alpha5] - 2018-08-13
### Fixed
- Rules with `all_day` set to `true` were wrongly normalized to UTC midnight times.

## [0.1-alpha4] - 2018-07-12
### Fixed
- When repeating until a specific date, no sessions would be generated for that date. 

## [0.1-alpha3] - 2018-06-11
### Fixed
- The weekly repeating rule has been re-written to repeat every day-of-the-week weekly.
- A extra session was being generated outside of the given repetition range.
- Session were being generated for an extra repetition period.

## [0.1-alpha2] - 2018-06-04
### Fixed
- Empty rule exclude dates were generating warnings.

## [0.1-alpha1] - 2018-05-21
Initial version.
