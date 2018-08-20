# Change log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [[*next-version*]] - YYYY-MM-DD
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
