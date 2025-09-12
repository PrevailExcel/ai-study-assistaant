from gtts import gTTS
import sys
import os

def synthesize(text, filename="summary.mp3", lang="en"):
    base_path = os.path.dirname(os.path.abspath(__file__))  # points to Laravel root
    output_path = os.path.join(base_path, "storage", "app", "public", filename)
    os.makedirs(os.path.dirname(output_path), exist_ok=True)

    tts = gTTS(text=text, lang=lang)
    tts.save(output_path)

    return output_path

if __name__ == "__main__":
    text = sys.argv[1]
    filename = sys.argv[2] if len(sys.argv) > 2 else "summary.mp3"
    path = synthesize(text, filename)
    print(path)
