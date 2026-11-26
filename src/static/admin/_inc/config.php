<?php
// One-time setup code for the lead tracker (hash only; the owner has the code).
// It is needed only once, to choose the admin password at /admin/ on first visit. After that it does nothing.
// To reset a forgotten password: delete leads-data/admin.json in hPanel > File Manager, then set up again
// (ask Claude to generate a new code and replace this hash).
const SETUP_CODE_HASH = '$2y$10$EOsNFk43JjXHQy6UhXOLeuU.vUrncUgIndKZMUP0Ut7358/bzKbNm';
