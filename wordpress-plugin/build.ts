#!/usr/bin/env bun
// Zips sdks/php/wordpress-plugin/metrixwire-apm/ into apps/web/public/downloads/metrixwire-apm.zip
// so the dashboard can serve it as a direct download (Settings → MetrixWire → install
// guide, same pattern as the Java agent jar). Runs as part of `bun run build` (root
// package.json), before Vite copies apps/web/public/ into apps/web/dist/.
//
// Hand-rolled STORE-only ZIP writer (crc32 + DOS headers, no compression) instead of
// shelling out to `zip` or adding a dependency — the deploy image isn't guaranteed to
// have a zip binary, and the plugin is a few KB, so compression isn't worth the risk.

import { readdirSync, statSync, readFileSync, mkdirSync, writeFileSync } from 'node:fs'
import { join, relative, sep } from 'node:path'

const PLUGIN_SLUG = 'metrixwire-apm'
const SRC_DIR = join(import.meta.dir, PLUGIN_SLUG)
const OUT_DIR = join(import.meta.dir, '..', '..', '..', 'apps', 'web', 'public', 'downloads')
const OUT_FILE = join(OUT_DIR, `${PLUGIN_SLUG}.zip`)

const CRC_TABLE = (() => {
  const table = new Uint32Array(256)
  for (let n = 0; n < 256; n++) {
    let c = n
    for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1
    table[n] = c >>> 0
  }
  return table
})()

function crc32(buf: Buffer): number {
  let crc = 0xffffffff
  for (let i = 0; i < buf.length; i++) crc = CRC_TABLE[(crc ^ buf[i]) & 0xff] ^ (crc >>> 8)
  return (crc ^ 0xffffffff) >>> 0
}

function toDosDateTime(date: Date): { time: number; date: number } {
  const time = ((date.getHours() & 0x1f) << 11) | ((date.getMinutes() & 0x3f) << 5) | (Math.floor(date.getSeconds() / 2) & 0x1f)
  const dosDate = ((Math.max(0, date.getFullYear() - 1980) & 0x7f) << 9) | (((date.getMonth() + 1) & 0xf) << 5) | (date.getDate() & 0x1f)
  return { time, date: dosDate }
}

function listFiles(dir: string): string[] {
  const out: string[] = []
  for (const entry of readdirSync(dir)) {
    const full = join(dir, entry)
    if (statSync(full).isDirectory()) out.push(...listFiles(full))
    else out.push(full)
  }
  return out
}

/** Builds a minimal valid ZIP (local headers + central directory + EOCD), STORE method only. */
function buildZip(files: string[], rootLabel: string): Buffer {
  const stamp = toDosDateTime(new Date())
  const localParts: Buffer[] = []
  const centralParts: Buffer[] = []
  let offset = 0

  for (const file of files) {
    const data = readFileSync(file)
    const nameInZip = `${rootLabel}/${relative(SRC_DIR, file).split(sep).join('/')}`
    const nameBuf = Buffer.from(nameInZip, 'utf8')
    const crc = crc32(data)

    const local = Buffer.alloc(30)
    local.writeUInt32LE(0x04034b50, 0) // local file header signature
    local.writeUInt16LE(20, 4) // version needed to extract
    local.writeUInt16LE(0, 6) // general purpose flag
    local.writeUInt16LE(0, 8) // compression method: store
    local.writeUInt16LE(stamp.time, 10)
    local.writeUInt16LE(stamp.date, 12)
    local.writeUInt32LE(crc, 14)
    local.writeUInt32LE(data.length, 18) // compressed size
    local.writeUInt32LE(data.length, 22) // uncompressed size
    local.writeUInt16LE(nameBuf.length, 26)
    local.writeUInt16LE(0, 28) // extra field length
    localParts.push(local, nameBuf, data)

    const central = Buffer.alloc(46)
    central.writeUInt32LE(0x02014b50, 0) // central directory header signature
    central.writeUInt16LE(20, 4) // version made by
    central.writeUInt16LE(20, 6) // version needed to extract
    central.writeUInt16LE(0, 8) // general purpose flag
    central.writeUInt16LE(0, 10) // compression method
    central.writeUInt16LE(stamp.time, 12)
    central.writeUInt16LE(stamp.date, 14)
    central.writeUInt32LE(crc, 16)
    central.writeUInt32LE(data.length, 20)
    central.writeUInt32LE(data.length, 24)
    central.writeUInt16LE(nameBuf.length, 28)
    central.writeUInt16LE(0, 30) // extra length
    central.writeUInt16LE(0, 32) // comment length
    central.writeUInt16LE(0, 34) // disk number start
    central.writeUInt16LE(0, 36) // internal attributes
    central.writeUInt32LE((0o100644 << 16) >>> 0, 38) // external attributes (unix rw-r--r--)
    central.writeUInt32LE(offset, 42) // offset of local header
    centralParts.push(central, nameBuf)

    offset += local.length + nameBuf.length + data.length
  }

  const centralStart = offset
  const centralBuf = Buffer.concat(centralParts)

  const end = Buffer.alloc(22)
  end.writeUInt32LE(0x06054b50, 0) // end of central directory signature
  end.writeUInt16LE(0, 4) // disk number
  end.writeUInt16LE(0, 6) // disk with central directory
  end.writeUInt16LE(files.length, 8) // entries on this disk
  end.writeUInt16LE(files.length, 10) // total entries
  end.writeUInt32LE(centralBuf.length, 12) // central directory size
  end.writeUInt32LE(centralStart, 16) // central directory offset
  end.writeUInt16LE(0, 20) // comment length

  return Buffer.concat([...localParts, centralBuf, end])
}

const files = listFiles(SRC_DIR)
const zip = buildZip(files, PLUGIN_SLUG)
mkdirSync(OUT_DIR, { recursive: true })
writeFileSync(OUT_FILE, zip)

const repoRoot = join(import.meta.dir, '..', '..', '..')
console.log(`Built ${relative(repoRoot, OUT_FILE)} (${files.length} files, ${zip.length} bytes)`)
