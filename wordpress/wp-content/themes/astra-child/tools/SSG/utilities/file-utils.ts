import fs from 'fs';
import path from 'path';

export type EncodingType = 'utf8' | 'ascii' | 'latin1' | 'base64' | 'hex' | 'binary' | 'ucs2';

export interface FileWriteOptions {
  createDirectories?: boolean;
  encoding?: EncodingType;
}

export class FileManager {
  static ensureDirectoryExists(dirPath: string): void {
    if (!fs.existsSync(dirPath)) {
      fs.mkdirSync(dirPath, { recursive: true });
    }
  }

  static writeFile(filePath: string, content: string, options: FileWriteOptions = {}): void {
    const { createDirectories = true, encoding = 'utf8' } = options;

    if (createDirectories) {
      const dir = path.dirname(filePath);
      this.ensureDirectoryExists(dir);
    }

    fs.writeFileSync(filePath, content, encoding);
  }

  static readFile(filePath: string, encoding: EncodingType = 'utf8'): string {
    return fs.readFileSync(filePath, encoding);
  }

  static fileExists(filePath: string): boolean {
    return fs.existsSync(filePath);
  }

  static getDirectorySize(dirPath: string): number {
    let totalSize = 0;

    function calculateSize(itemPath: string): void {
      const stats = fs.statSync(itemPath);

      if (stats.isDirectory()) {
        const items = fs.readdirSync(itemPath);
        items.forEach(item => {
          calculateSize(path.join(itemPath, item));
        });
      } else {
        totalSize += stats.size;
      }
    }

    if (fs.existsSync(dirPath)) {
      calculateSize(dirPath);
    }

    return totalSize;
  }

  static formatFileSize(bytes: number): string {
    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let unitIndex = 0;

    while (size >= 1024 && unitIndex < units.length - 1) {
      size /= 1024;
      unitIndex++;
    }

    return `${size.toFixed(1)} ${units[unitIndex]}`;
  }

  static getFileCount(dirPath: string, extension?: string): number {
    let count = 0;

    function countFiles(itemPath: string): void {
      const stats = fs.statSync(itemPath);

      if (stats.isDirectory()) {
        const items = fs.readdirSync(itemPath);
        items.forEach(item => {
          countFiles(path.join(itemPath, item));
        });
      } else if (!extension || itemPath.endsWith(extension)) {
        count++;
      }
    }

    if (fs.existsSync(dirPath)) {
      countFiles(dirPath);
    }

    return count;
  }
}

export class UrlToFilePathConverter {
  static convert(url: string, outputDir: string): string {
    try {
      const urlObj = new URL(url);

      // Get relative path from URL
      let relativePath = urlObj.pathname;

      // Remove leading slash
      if (relativePath.startsWith('/')) {
        relativePath = relativePath.slice(1);
      }

      // Remove trailing slash except for root
      if (relativePath !== '' && relativePath.endsWith('/')) {
        relativePath = relativePath.slice(0, -1);
      }

      // Handle root path
      if (relativePath === '') {
        return path.join(outputDir, 'index.html');
      }

      // Convert to file path
      const filePath = relativePath + (relativePath.endsWith('.html') ? '' : '.html');
      return path.join(outputDir, filePath);
    } catch (error) {
      console.error(`Error converting URL to file path: ${url}`, error);
      return path.join(outputDir, 'index.html');
    }
  }
}

// Convenience functions
export function ensureDirectory(dirPath: string): void {
  FileManager.ensureDirectoryExists(dirPath);
}

export function writeFile(filePath: string, content: string, options?: FileWriteOptions): void {
  FileManager.writeFile(filePath, content, options);
}

export function readFile(filePath: string, encoding?: EncodingType): string {
  return FileManager.readFile(filePath, encoding);
}

export function fileExists(filePath: string): boolean {
  return FileManager.fileExists(filePath);
}

export function urlToFilePath(url: string, outputDir: string): string {
  return UrlToFilePathConverter.convert(url, outputDir);
}

export function getDirectoryStats(dirPath: string): {
  fileCount: number;
  htmlFileCount: number;
  totalSize: number;
  formattedSize: string;
} {
  const fileCount = FileManager.getFileCount(dirPath);
  const htmlFileCount = FileManager.getFileCount(dirPath, '.html');
  const totalSize = FileManager.getDirectorySize(dirPath);
  const formattedSize = FileManager.formatFileSize(totalSize);

  return {
    fileCount,
    htmlFileCount,
    totalSize,
    formattedSize
  };
}