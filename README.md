# 60 Second Care

60 Second Care is an MVP health-check web application built with PHP, MySQL, JavaScript, Tailwind CSS, Google Gemini, and an interactive 3D body model.

The product goal is simple: collect better symptom information in under a minute, use that structured information to estimate urgency, recommend next steps, and, where useful, suggest a specific home or online-orderable test that helps the user take action.

This is not a diagnostic system. It gives guidance only and does not replace professional medical advice.

## MVP Status

This project is an MVP. The current version proves the main workflow:

- collect profile and symptom data
- let users select a body area using a 3D model
- ask follow-up questions based on the selected area
- run rule-based red-flag checks before AI
- send structured data to Gemini
- return urgency, possible causes, recommended actions, and one useful test when appropriate
- allow anonymous storage only after user consent

Future versions should improve anatomical precision, clinical validation, test-provider integration, location-based health data, accessibility, and safety review.

## Architecture Overview

The app is a traditional PHP multi-page application designed for XAMPP/Apache.

High-level flow:

```text
User Browser
   |
   v
PHP Screens
index.php -> describe.php -> location.php/followup.php -> loading.php
   |
   v
PHP Session State
profile + issue + location + followup + context
   |
   v
api/health-check.php
   |
   +--> Rule-based red-flag engine
   |
   +--> Gemini API if no emergency red flags
   |
   v
result.php
   |
   +--> optional anonymous database save with consent
