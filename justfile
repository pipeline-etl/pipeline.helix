# SPDX-FileCopyrightText: Copyright 2025 Framna Netherlands B.V., Zwolle, The Netherlands
# SPDX-License-Identifier: CC0-1.0

import 'vendor/framna-nl/php-library-justfiles/common.justfile'

set allow-duplicate-variables

export github_actions := env('GITHUB_ACTIONS', '0')
export default_coding_standard := 'Lunr'
