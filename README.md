# observium-ce-archive

[![archive](https://github.com/mews-se/observium-ce-archive/actions/workflows/archive.yml/badge.svg)](https://github.com/mews-se/observium-ce-archive/actions/workflows/archive.yml)
[![license](https://img.shields.io/badge/license-MIT-green)](LICENSE)

Archive of [Observium Community Edition](https://www.observium.org/) release
tarballs. Not affiliated with or endorsed by Observium Limited.

Observium ships CE as a single `observium-community-latest.tar.gz` that is
overwritten in place. Once a new build lands, the previous one is gone — there
is no release page and no version history to fall back on. This repository
keeps a copy: a workflow fetches the tarball every week, compares it against
the last archived one, and publishes any build it hasn't seen as a release
tagged with the CE version it reports.

Tarballs are archived byte for byte, licence and notices included, exactly as
Observium published them. The MIT licence here covers the workflow and this
README, nothing else — the archived software is under the Observium licence
found inside each tarball.

## Using an archived build

Download a tarball from the releases page, check it and unpack it:

```
sha256sum -c observium-community-26.1.14545.tar.gz.sha256
tar zxf observium-community-26.1.14545.tar.gz -C /opt
```

The CE version in the tag comes from `includes/definitions/version.inc.php`
inside the tarball, so `v26.1.14545` is the exact build Observium shipped. When
a rebuild carries the same version number but different bytes, the second one
gets a date suffix rather than replacing the first.

## Why

[docker-observium](https://github.com/mews-se/docker-observium) builds its
image straight from observium.org, which is fine until that URL moves or the
site is down on a build day. This archive is the fallback, and it doubles as a
way to diff two CE builds — unpack both and compare, since upstream develops
out of sight.
