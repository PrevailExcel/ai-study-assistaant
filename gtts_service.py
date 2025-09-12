from gtts import gTTS
import sys
import os

def synthesize(text, filename="summary.mp3", lang="en"):
    tts = gTTS(text=text, lang=lang)
    output_path = os.path.join("storage", "app", "public", filename)
    os.makedirs(os.path.dirname(output_path), exist_ok=True)
    tts.save(output_path)
    return output_path

if __name__ == "__main__":
    text = sys.argv[1]
    filename = sys.argv[2] if len(sys.argv) > 2 else "summary.mp3"
    path = synthesize(text, filename)
    print(path)
