/**
 * Lightweight in-browser XLSX generator without third-party dependencies.
 * Produces standard OpenXML (.xlsx) files formatted with styles, column widths, and frozen headers.
 */

export interface XlsxColumn {
    title: string;
    key?: string;
    align?: "left" | "center" | "right" | "start" | "end";
    width?: number;
}

export interface GenerateXlsxOptions {
    columns: XlsxColumn[];
    rows: any[][];
    sheetName?: string;
    fileName?: string;
}

const makeCrcTable = (): Uint32Array => {
    const table = new Uint32Array(256);
    for (let i = 0; i < 256; i++) {
        let c = i;
        for (let k = 0; k < 8; k++) {
            c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
        }
        table[i] = c;
    }
    return table;
};

const crcTable = makeCrcTable();

const calculateCrc32 = (bytes: Uint8Array): number => {
    let crc = 0 ^ -1;
    for (let i = 0; i < bytes.length; i++) {
        crc = (crc >>> 8) ^ crcTable[(crc ^ bytes[i]) & 0xff];
    }
    return (crc ^ -1) >>> 0;
};

class SimpleZip {
    private files: {
        name: string;
        nameBytes: Uint8Array;
        data: Uint8Array;
        crc: number;
        size: number;
    }[] = [];

    addFile(name: string, content: string | Uint8Array): void {
        const encoder = new TextEncoder();
        const data =
            typeof content === "string" ? encoder.encode(content) : content;
        this.files.push({
            name,
            nameBytes: encoder.encode(name),
            data,
            crc: calculateCrc32(data),
            size: data.length,
        });
    }

    generate(): Uint8Array {
        let totalSize = 0;
        let cdSize = 0;

        for (const file of this.files) {
            totalSize += 30 + file.nameBytes.length + file.size;
            cdSize += 46 + file.nameBytes.length;
        }

        totalSize += cdSize + 22;

        const out = new Uint8Array(totalSize);
        const view = new DataView(out.buffer);

        let localOffset = 0;
        const localOffsets: number[] = [];

        // 1. Write Local File Headers + File Data
        for (const file of this.files) {
            localOffsets.push(localOffset);

            view.setUint32(localOffset + 0, 0x04034b50, true);
            view.setUint16(localOffset + 4, 20, true);
            view.setUint16(localOffset + 6, 0x0800, true);
            view.setUint16(localOffset + 8, 0, true);
            view.setUint16(localOffset + 10, 0, true);
            view.setUint16(localOffset + 12, 0, true);
            view.setUint32(localOffset + 14, file.crc, true);
            view.setUint32(localOffset + 18, file.size, true);
            view.setUint32(localOffset + 22, file.size, true);
            view.setUint16(localOffset + 26, file.nameBytes.length, true);
            view.setUint16(localOffset + 28, 0, true);

            out.set(file.nameBytes, localOffset + 30);
            out.set(file.data, localOffset + 30 + file.nameBytes.length);

            localOffset += 30 + file.nameBytes.length + file.size;
        }

        // 2. Write Central Directory Headers
        const cdOffset = localOffset;
        let cdPointer = cdOffset;

        for (let i = 0; i < this.files.length; i++) {
            const file = this.files[i];
            const fileHeaderOffset = localOffsets[i];

            view.setUint32(cdPointer + 0, 0x02014b50, true);
            view.setUint16(cdPointer + 4, 20, true);
            view.setUint16(cdPointer + 6, 20, true);
            view.setUint16(cdPointer + 8, 0x0800, true);
            view.setUint16(cdPointer + 10, 0, true);
            view.setUint16(cdPointer + 12, 0, true);
            view.setUint16(cdPointer + 14, 0, true);
            view.setUint32(cdPointer + 16, file.crc, true);
            view.setUint32(cdPointer + 20, file.size, true);
            view.setUint32(cdPointer + 24, file.size, true);
            view.setUint16(cdPointer + 28, file.nameBytes.length, true);
            view.setUint16(cdPointer + 30, 0, true);
            view.setUint16(cdPointer + 32, 0, true);
            view.setUint16(cdPointer + 34, 0, true);
            view.setUint16(cdPointer + 36, 0, true);
            view.setUint32(cdPointer + 38, 0, true);
            view.setUint32(cdPointer + 42, fileHeaderOffset, true);

            out.set(file.nameBytes, cdPointer + 46);
            cdPointer += 46 + file.nameBytes.length;
        }

        // 3. Write End of Central Directory Record (EOCD)
        const eocdOffset = cdPointer;
        view.setUint32(eocdOffset + 0, 0x06054b50, true);
        view.setUint16(eocdOffset + 4, 0, true);
        view.setUint16(eocdOffset + 6, 0, true);
        view.setUint16(eocdOffset + 8, this.files.length, true);
        view.setUint16(eocdOffset + 10, this.files.length, true);
        view.setUint32(eocdOffset + 12, cdSize, true);
        view.setUint32(eocdOffset + 16, cdOffset, true);
        view.setUint16(eocdOffset + 20, 0, true);

        return out;
    }
}

const getColLetter = (colIndex: number): string => {
    let letter = "";
    let temp = colIndex + 1;
    while (temp > 0) {
        const rem = (temp - 1) % 26;
        letter = String.fromCharCode(65 + rem) + letter;
        temp = Math.floor((temp - 1) / 26);
    }
    return letter;
};

const escapeXml = (str: any): string => {
    if (str === null || str === undefined) return "";
    return String(str)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&apos;");
};

export const downloadXlsx = (options: GenerateXlsxOptions): void => {
    const {
        columns,
        rows,
        sheetName = "Data",
        fileName = "export.xlsx",
    } = options;
    const zip = new SimpleZip();

    zip.addFile(
        "[Content_Types].xml",
        `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>`,
    );

    zip.addFile(
        "_rels/.rels",
        `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>`,
    );

    zip.addFile(
        "xl/_rels/workbook.xml.rels",
        `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>`,
    );

    zip.addFile(
        "xl/workbook.xml",
        `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="${escapeXml(sheetName)}" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>`,
    );

    zip.addFile(
        "xl/styles.xml",
        `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font>
      <sz val="11"/>
      <name val="Calibri"/>
    </font>
    <font>
      <b/>
      <sz val="11"/>
      <color rgb="FF1E293B"/>
      <name val="Calibri"/>
    </font>
  </fonts>
  <fills count="3">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill>
      <patternFill patternType="solid">
        <fgColor rgb="FFF1F5F9"/>
      </patternFill>
    </fill>
  </fills>
  <borders count="2">
    <border><left/><right/><top/><bottom/><diagonal/></border>
    <border>
      <left style="thin"><color rgb="FFE2E8F0"/></left>
      <right style="thin"><color rgb="FFE2E8F0"/></right>
      <top style="thin"><color rgb="FFE2E8F0"/></top>
      <bottom style="thin"><color rgb="FFE2E8F0"/></bottom>
    </border>
  </borders>
  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>
  <cellXfs count="5">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="center" vertical="center" wrapText="1"/>
    </xf>
    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="left" vertical="center"/>
    </xf>
    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="right" vertical="center"/>
    </xf>
    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="center" vertical="center"/>
    </xf>
  </cellXfs>
</styleSheet>`,
    );

    // Compute column widths based on headers and cell contents
    const colWidths = columns.map((c) =>
        Math.max(String(c.title || "").length + 4, 12),
    );
    rows.forEach((r) => {
        r.forEach((val, i) => {
            if (val !== null && val !== undefined) {
                const len = String(val).length + 3;
                if (len > colWidths[i]) {
                    colWidths[i] = Math.min(len, 50);
                }
            }
        });
    });

    let sheetXml = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetViews>
    <sheetView tabSelected="1" workbookViewId="0">
      <pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>
    </sheetView>
  </sheetViews>
  <cols>`;

    colWidths.forEach((w, i) => {
        sheetXml += `<col min="${i + 1}" max="${i + 1}" width="${w}" customWidth="1"/>`;
    });
    sheetXml += `</cols>
  <sheetData>
    <row r="1" ht="26" customHeight="1">`;

    columns.forEach((col, idx) => {
        const cellRef = `${getColLetter(idx)}1`;
        sheetXml += `<c r="${cellRef}" t="inlineStr" s="1"><is><t>${escapeXml(col.title)}</t></is></c>`;
    });
    sheetXml += `</row>`;

    rows.forEach((row, rowIdx) => {
        const rNum = rowIdx + 2;
        sheetXml += `<row r="${rNum}" ht="20" customHeight="1">`;
        row.forEach((val, colIdx) => {
            const cellRef = `${getColLetter(colIdx)}${rNum}`;
            const colDef = columns[colIdx];
            const align = colDef?.align || "left";
            const isNum = typeof val === "number" && !isNaN(val);
            const styleId = isNum
                ? 3
                : align === "center"
                  ? 4
                  : 2;

            if (isNum) {
                sheetXml += `<c r="${cellRef}" s="${styleId}"><v>${val}</v></c>`;
            } else if (val === null || val === undefined || val === "") {
                sheetXml += `<c r="${cellRef}" s="${styleId}"/>`;
            } else {
                sheetXml += `<c r="${cellRef}" t="inlineStr" s="${styleId}"><is><t xml:space="preserve">${escapeXml(val)}</t></is></c>`;
            }
        });
        sheetXml += `</row>`;
    });

    sheetXml += `  </sheetData>
</worksheet>`;

    zip.addFile("xl/worksheets/sheet1.xml", sheetXml);

    const data = zip.generate();
    const blob = new Blob([data.buffer as ArrayBuffer], {
        type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });

    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    const safeFileName = fileName.endsWith(".xlsx")
        ? fileName
        : `${fileName}.xlsx`;
    link.setAttribute("href", url);
    link.setAttribute("download", safeFileName);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
};
