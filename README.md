# observium-ce-archive

[![archive](https://github.com/mews-se/observium-ce-archive/actions/workflows/archive.yml/badge.svg)](https://github.com/mews-se/observium-ce-archive/actions/workflows/archive.yml)
[![license](https://img.shields.io/badge/license-MIT-green)](LICENSE)

Archive of [Observium Community Edition](https://www.observium.org/). Not
affiliated with or endorsed by Observium Limited.

Observium ships CE as a single `observium-community-latest.tar.gz` that is
overwritten in place. Once a new build lands the previous one is gone — there
is no release page and no version history to fall back on.

This repository keeps both halves of that history. The tree holds the current
build unpacked, so any file can be fetched directly, and every build is also
published as a release with the original tarball attached. A workflow checks
upstream weekly, compares the tarball against the last archived one and does
nothing unless the bytes changed.

The code is archived exactly as Observium published it. Nothing is patched or
stubbed, which is the point of an archive — if a build behaves oddly, that is
how it shipped.

## Grabbing a single file

```
curl -O https://raw.githubusercontent.com/mews-se/observium-ce-archive/main/scripts/distro
```

## Grabbing a whole build

Releases carry the untouched tarball and its checksum:

```
sha256sum -c observium-community-26.1.14545.tar.gz.sha256
tar zxf observium-community-26.1.14545.tar.gz -C /opt
```

Tags come from `OBSERVIUM_VERSION` in `includes/definitions/version.inc.php`,
so `v26.1.14545` is the exact build Observium shipped. If a rebuild carries the
same version number but different bytes, the second one gets a date suffix
instead of replacing the first.

## Why

[docker-observium](https://github.com/mews-se/docker-observium) builds its
image straight from observium.org, which is fine until that URL moves or the
site is down on a build day. This archive is the fallback, and having the tree
in git means two builds can be diffed — useful, since upstream develops out of
sight in a private repository.

The MIT licence here covers the workflow and this README. The archived software
is Observium's, under the licence included with it.
