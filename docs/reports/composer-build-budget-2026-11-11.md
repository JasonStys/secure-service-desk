# Native Composer build budget correction

The first clean-main rebuild stopped in Composer's optimized autoload generation because the
shared PHP argument builder supplied the application's30-second request limit. The outer build
process already allowed180 seconds. This was a real build-policy error, not a failing ticket
workflow or an application-data error.

Only the trusted Composer CLI process now receives `-d max_execution_time=120`, after the shared
defaults and before its fixed PHAR/install argv. The external180-second deadline remains finite
and unchanged. Runtime CGI keeps `max_execution_time=30`; neither global PHP settings nor an ini
file is modified. Production-only installation still disables plugins/scripts and uses the exact
locked graph in its isolated build directory.

The native regression checks effective final PHP settings for both contexts, confirms the120-second
build limit is below the180-second process limit, preserves the runtime network/URL policy and
requires the fixed production-install command. All15 native contract tests and the67-source
repository/index gates passed locally. Corrected source must pass its full hosted original
and extracted-native gates before root-coordinated clean-main distribution.
