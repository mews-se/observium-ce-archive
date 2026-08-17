<?php

      $deleted = db_cache_cleanup();
      if ($deleted > 0) {
          echo "Cleaned up $deleted expired cache entries.\n";
      }
