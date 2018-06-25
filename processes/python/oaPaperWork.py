import sys
import time
import os
import re
from pyPdf import PdfFileWriter,PdfFileReader

class OaPaperWork():
    def __init__(self):
        self.output = PdfFileWriter()
        self.gbl = sys.argv[1]
        self.year = time.strftime("%Y")
        self.path = "/scan/fPImages/" + self.year + "/GOVDOC/" + self.gbl + "/"
        self.outPutDir = "/scan/silo/DocCon/oapaperwork/" + self.gbl + "/"
        self.patterns = ["WeightTickets","GBL-RATED","DD619-Orig","HouseHold"]
        self.mergeFiles = []
        self.getFiles()
        self.doMerge()
    def getFiles(self):
        if os.path.isdir(self.path):
            for f in os.listdir(self.path):
                for p in self.patterns:
                    if re.match(p,f):
                        self.mergeFiles.append(self.path + f)
        else:
            return False

    def append_pdf(self,input,output):
        [self.output.addPage(input.getPage(page_num)) for page_num in range(input.numPages)]

    def doMerge(self):
        outPutFile = self.outPutDir + self.gbl + "_Docs.pdf"
        if not os.path.isdir(self.outPutDir):
            os.mkdir(self.outPutDir,0777)
        for f in self.mergeFiles:
            self.append_pdf(PdfFileReader(open(f,"rd")),self.output)
        self.output.write(open(outPutFile,"wb"))

app = OaPaperWork()
